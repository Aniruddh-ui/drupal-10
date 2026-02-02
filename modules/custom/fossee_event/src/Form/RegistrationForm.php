<?php

declare(strict_types=1);

namespace Drupal\fossee_event\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\fossee_event\Service\EventService;
use Drupal\fossee_event\Service\RegistrationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Public registration form for event participants.
 *
 * Architectural Decision: This form uses AJAX to create cascading dropdowns:
 * Category -> Event Date -> Event Name. The AJAX callbacks are kept minimal,
 * delegating all query logic to EventService. Validation includes duplicate
 * checking (email + event_date) and regex sanitization for special characters.
 *
 * Access Control: The form checks if there are active events (within their
 * registration window) before allowing access.
 */
class RegistrationForm extends FormBase
{

    /**
     * The event service.
     *
     * @var \Drupal\fossee_event\Service\EventService
     */
    protected EventService $eventService;

    /**
     * The registration service.
     *
     * @var \Drupal\fossee_event\Service\RegistrationService
     */
    protected RegistrationService $registrationService;

    /**
     * The time service.
     *
     * @var \Drupal\Component\Datetime\TimeInterface
     */
    protected TimeInterface $time;

    /**
     * Constructs a RegistrationForm object.
     *
     * @param \Drupal\fossee_event\Service\EventService $eventService
     *   The event service for querying events.
     * @param \Drupal\fossee_event\Service\RegistrationService $registrationService
     *   The registration service for saving submissions.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     */
    public function __construct(
        EventService $eventService,
        RegistrationService $registrationService,
        TimeInterface $time
    ) {
        $this->eventService = $eventService;
        $this->registrationService = $registrationService;
        $this->time = $time;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): static
    {
        return new static(
            $container->get('fossee_event.event_service'),
            $container->get('fossee_event.registration_service'),
            $container->get('datetime.time')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'fossee_event_registration_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {
        // Check if there are any active events.
        $active_events = $this->eventService->getActiveEvents();
        if (empty($active_events)) {
            $form['no_events'] = [
                '#markup' => '<div class="messages messages--warning">' .
                    $this->t('There are currently no events open for registration. Please check back later.') .
                    '</div>',
            ];
            return $form;
        }

        // Get available categories from active events.
        $categories = $this->getActiveCategoriesOptions();

        // Get current form state values for AJAX rebuilt.
        $selected_category = $form_state->getValue('category', '');
        $selected_event_date = $form_state->getValue('event_date', '');

        // Participant Name field.
        $form['name'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Full Name'),
            '#description' => $this->t('Enter your full name. Only letters, spaces, and hyphens allowed.'),
            '#required' => TRUE,
            '#maxlength' => 255,
        ];

        // Participant Email field.
        $form['email'] = [
            '#type' => 'email',
            '#title' => $this->t('Email Address'),
            '#description' => $this->t('Enter a valid email address.'),
            '#required' => TRUE,
            '#maxlength' => 255,
        ];

        // Participant Department field.
        $form['department'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Department / Organization'),
            '#description' => $this->t('Enter your department or organization. Only letters, numbers, spaces, and hyphens allowed.'),
            '#required' => TRUE,
            '#maxlength' => 255,
        ];

        // Category dropdown - triggers AJAX to load event dates.
        $form['category'] = [
            '#type' => 'select',
            '#title' => $this->t('Event Category'),
            '#options' => $categories,
            '#empty_option' => $this->t('- Select Category -'),
            '#required' => TRUE,
            '#ajax' => [
                'callback' => '::updateEventDatesCallback',
                'wrapper' => 'event-date-wrapper',
                'event' => 'change',
            ],
        ];

        // Event Date dropdown - populated via AJAX based on category.
        $event_dates = [];
        if (!empty($selected_category)) {
            $event_dates = $this->getEventDatesOptions($selected_category);
        }

        $form['event_date_wrapper'] = [
            '#type' => 'container',
            '#attributes' => ['id' => 'event-date-wrapper'],
        ];

        $form['event_date_wrapper']['event_date'] = [
            '#type' => 'select',
            '#title' => $this->t('Event Date'),
            '#options' => $event_dates,
            '#empty_option' => $this->t('- Select Event Date -'),
            '#required' => TRUE,
            '#validated' => TRUE,
            '#ajax' => [
                'callback' => '::updateEventNamesCallback',
                'wrapper' => 'event-name-wrapper',
                'event' => 'change',
            ],
        ];

        // Event Name dropdown - populated via AJAX based on category + date.
        $event_names = [];
        if (!empty($selected_category) && !empty($selected_event_date)) {
            $event_names = $this->getEventNamesOptions($selected_category, (int) $selected_event_date);
        }

        $form['event_name_wrapper'] = [
            '#type' => 'container',
            '#attributes' => ['id' => 'event-name-wrapper'],
        ];

        $form['event_name_wrapper']['event_id'] = [
            '#type' => 'select',
            '#title' => $this->t('Event Name'),
            '#options' => $event_names,
            '#empty_option' => $this->t('- Select Event -'),
            '#required' => TRUE,
            '#validated' => TRUE,
        ];

        // Submit button.
        $form['actions'] = [
            '#type' => 'actions',
        ];
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Register'),
            '#button_type' => 'primary',
        ];

        return $form;
    }

    /**
     * AJAX callback: Updates the event date dropdown based on selected category.
     *
     * @param array $form
     *   The form array.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The form state.
     *
     * @return \Drupal\Core\Ajax\AjaxResponse
     *   The AJAX response containing both date and name wrapper updates.
     */
    public function updateEventDatesCallback(array &$form, FormStateInterface $form_state): AjaxResponse
    {
        $response = new AjaxResponse();

        // Replace the event date dropdown.
        $response->addCommand(new ReplaceCommand(
            '#event-date-wrapper',
            $form['event_date_wrapper']
        ));

        // Also reset the event name dropdown since category changed.
        $response->addCommand(new ReplaceCommand(
            '#event-name-wrapper',
            $form['event_name_wrapper']
        ));

        return $response;
    }

    /**
     * AJAX callback: Updates the event name dropdown based on selected date.
     *
     * @param array $form
     *   The form array.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The form state.
     *
     * @return \Drupal\Core\Ajax\AjaxResponse
     *   The AJAX response.
     */
    public function updateEventNamesCallback(array &$form, FormStateInterface $form_state): AjaxResponse
    {
        $response = new AjaxResponse();

        $response->addCommand(new ReplaceCommand(
            '#event-name-wrapper',
            $form['event_name_wrapper']
        ));

        return $response;
    }

    /**
     * Gets category options from active events only.
     *
     * @return array
     *   Associative array of category => category.
     */
    protected function getActiveCategoriesOptions(): array
    {
        $now = $this->time->getRequestTime();
        $categories = $this->eventService->getCategories();

        // Filter to only categories with active events.
        $active_categories = [];
        foreach ($categories as $category) {
            $dates = $this->eventService->getDatesByCategory($category);
            foreach ($dates as $date) {
                $events = $this->eventService->getEventsByDateAndCategory($category, (int) $date);
                foreach ($events as $event_id => $event_name) {
                    $event = $this->eventService->loadEvent((int) $event_id);
                    if ($event && $event['start_date'] <= $now && $event['end_date'] >= $now) {
                        $active_categories[$category] = $category;
                        break 2;
                    }
                }
            }
        }

        return $active_categories;
    }

    /**
     * Gets event date options for a given category.
     *
     * @param string $category
     *   The selected category.
     *
     * @return array
     *   Associative array of timestamp => formatted date.
     */
    protected function getEventDatesOptions(string $category): array
    {
        $dates = $this->eventService->getDatesByCategory($category);
        $options = [];

        foreach ($dates as $date) {
            $timestamp = (int) $date;
            $options[$timestamp] = date('Y-m-d', $timestamp);
        }

        return $options;
    }

    /**
     * Gets event name options for a given category and date.
     *
     * @param string $category
     *   The selected category.
     * @param int $event_date
     *   The selected event date timestamp.
     *
     * @return array
     *   Associative array of event_id => event_name.
     */
    protected function getEventNamesOptions(string $category, int $event_date): array
    {
        return $this->eventService->getEventsByDateAndCategory($category, $event_date);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state): void
    {
        parent::validateForm($form, $form_state);

        // Skip validation if no events available.
        if (empty($this->eventService->getActiveEvents())) {
            return;
        }

        // Validate name: No special characters (only letters, spaces, hyphens).
        $name = $form_state->getValue('name');
        if (!empty($name) && !preg_match('/^[a-zA-Z\s\-]+$/', $name)) {
            $form_state->setErrorByName('name', $this->t('Name can only contain letters, spaces, and hyphens.'));
        }

        // Validate department: No special characters.
        $department = $form_state->getValue('department');
        if (!empty($department) && !preg_match('/^[a-zA-Z0-9\s\-]+$/', $department)) {
            $form_state->setErrorByName('department', $this->t('Department can only contain letters, numbers, spaces, and hyphens.'));
        }

        // Validate email format (Drupal handles this via #type => 'email').
        $email = $form_state->getValue('email');

        // Get event_date from the wrapper.
        $event_date = $form_state->getValue('event_date');

        // Duplicate check: Email + Event Date must be unique.
        if (!empty($email) && !empty($event_date)) {
            if ($this->registrationService->isDuplicate($email, (int) $event_date)) {
                $form_state->setErrorByName('email', $this->t('You have already registered for an event on this date.'));
            }
        }

        // Validate that selected event is still within registration window.
        $event_id = $form_state->getValue('event_id');
        if (!empty($event_id)) {
            $event = $this->eventService->loadEvent((int) $event_id);
            if ($event) {
                $now = $this->time->getRequestTime();
                if ($now < $event['start_date'] || $now > $event['end_date']) {
                    $form_state->setErrorByName('event_id', $this->t('Registration for this event is no longer available.'));
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $event_id = (int) $form_state->getValue('event_id');
        $event_date = (int) $form_state->getValue('event_date');

        $data = [
            'event_id' => $event_id,
            'name' => $form_state->getValue('name'),
            'email' => $form_state->getValue('email'),
            'department' => $form_state->getValue('department'),
            'event_date' => $event_date,
        ];

        // Save registration via service.
        $registration_id = $this->registrationService->saveRegistration($data);

        // Load event name for the success message.
        $event = $this->eventService->loadEvent($event_id);
        $event_name = $event ? $event['event_name'] : 'Unknown Event';

        // Send confirmation emails.
        $data['event_name'] = $event_name;
        $data['registration_id'] = $registration_id;
        $this->registrationService->sendUserConfirmation($data);
        $this->registrationService->sendAdminNotification($data);

        // Success message.
        $this->messenger()->addStatus($this->t('Thank you, @name! Your registration for "@event" has been submitted successfully.', [
            '@name' => $data['name'],
            '@event' => $event_name,
        ]));

        // Optionally redirect or rebuild.
        $form_state->setRedirect('<front>');
    }

}
