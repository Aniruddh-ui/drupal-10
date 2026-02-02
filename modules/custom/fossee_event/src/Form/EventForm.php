<?php

declare(strict_types=1);

namespace Drupal\fossee_event\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\fossee_event\Service\EventService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for administrators to create new events.
 *
 * Architectural Decision: This is a content-creation form (FormBase), not a
 * configuration form. Event data is stored in a custom database table, not
 * in the Config API. The form uses dependency injection for the EventService
 * to avoid \Drupal:: static calls.
 */
class EventForm extends FormBase
{

    /**
     * The event service.
     *
     * @var \Drupal\fossee_event\Service\EventService
     */
    protected EventService $eventService;

    /**
     * Constructs an EventForm object.
     *
     * @param \Drupal\fossee_event\Service\EventService $eventService
     *   The event service for database operations.
     */
    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): static
    {
        return new static(
            $container->get('fossee_event.event_service')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'fossee_event_event_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {
        // Event Name field.
        $form['event_name'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Event Name'),
            '#description' => $this->t('Enter the name of the event. Only alphanumeric characters, spaces, and hyphens allowed.'),
            '#required' => TRUE,
            '#maxlength' => 255,
        ];

        // Category field.
        $form['category'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Category'),
            '#description' => $this->t('Enter the event category (e.g., Workshop, Seminar, Conference).'),
            '#required' => TRUE,
            '#maxlength' => 128,
        ];

        // Start Date field - Registration window opens.
        $form['start_date'] = [
            '#type' => 'datetime',
            '#title' => $this->t('Registration Start Date'),
            '#description' => $this->t('The date when registration opens for this event.'),
            '#required' => TRUE,
        ];

        // End Date field - Registration window closes.
        $form['end_date'] = [
            '#type' => 'datetime',
            '#title' => $this->t('Registration End Date'),
            '#description' => $this->t('The date when registration closes. Must be on or after the start date.'),
            '#required' => TRUE,
        ];

        // Event Date field - The actual event date.
        $form['event_date'] = [
            '#type' => 'datetime',
            '#title' => $this->t('Event Date'),
            '#description' => $this->t('The date when the event will take place.'),
            '#required' => TRUE,
        ];

        // Submit button.
        $form['actions'] = [
            '#type' => 'actions',
        ];
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Create Event'),
            '#button_type' => 'primary',
        ];

        // Display existing events in a table for admin reference.
        $form['existing_events'] = $this->buildExistingEventsTable();

        return $form;
    }

    /**
     * Builds a table displaying existing events.
     *
     * This provides admins with visibility into what events already exist,
     * useful for debugging and avoiding duplicate entries.
     *
     * @return array
     *   A render array containing the events table.
     */
    protected function buildExistingEventsTable(): array
    {
        $events = $this->eventService->getAllEvents();

        $header = [
            $this->t('ID'),
            $this->t('Event Name'),
            $this->t('Category'),
            $this->t('Start Date'),
            $this->t('End Date'),
            $this->t('Event Date'),
        ];

        $rows = [];
        foreach ($events as $event) {
            $rows[] = [
                $event->id,
                // Using render arrays and proper escaping for XSS prevention.
                ['data' => ['#markup' => htmlspecialchars($event->event_name, ENT_QUOTES, 'UTF-8')]],
                ['data' => ['#markup' => htmlspecialchars($event->category, ENT_QUOTES, 'UTF-8')]],
                date('Y-m-d H:i', (int) $event->start_date),
                date('Y-m-d H:i', (int) $event->end_date),
                date('Y-m-d H:i', (int) $event->event_date),
            ];
        }

        return [
            '#type' => 'details',
            '#title' => $this->t('Existing Events (@count)', ['@count' => count($events)]),
            '#open' => TRUE,
            'table' => [
                '#type' => 'table',
                '#header' => $header,
                '#rows' => $rows,
                '#empty' => $this->t('No events have been created yet.'),
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state): void
    {
        parent::validateForm($form, $form_state);

        // Validate event_name: No special characters (only alphanumeric, spaces, hyphens).
        $event_name = $form_state->getValue('event_name');
        if (!preg_match('/^[a-zA-Z0-9\s\-]+$/', $event_name)) {
            $form_state->setErrorByName('event_name', $this->t('Event name can only contain letters, numbers, spaces, and hyphens.'));
        }

        // Validate category: No special characters.
        $category = $form_state->getValue('category');
        if (!preg_match('/^[a-zA-Z0-9\s\-]+$/', $category)) {
            $form_state->setErrorByName('category', $this->t('Category can only contain letters, numbers, spaces, and hyphens.'));
        }

        // Get date values.
        $start_date = $form_state->getValue('start_date');
        $end_date = $form_state->getValue('end_date');
        $event_date = $form_state->getValue('event_date');

        // Convert DrupalDateTime objects to timestamps for comparison.
        $start_timestamp = $start_date instanceof DrupalDateTime ? $start_date->getTimestamp() : 0;
        $end_timestamp = $end_date instanceof DrupalDateTime ? $end_date->getTimestamp() : 0;
        $event_timestamp = $event_date instanceof DrupalDateTime ? $event_date->getTimestamp() : 0;

        // Validate: End Date >= Start Date.
        if ($end_timestamp < $start_timestamp) {
            $form_state->setErrorByName('end_date', $this->t('Registration end date must be on or after the start date.'));
        }

        // Validate: Event Date should be reasonable (optional business rule).
        if ($event_timestamp < $start_timestamp) {
            $form_state->setErrorByName('event_date', $this->t('Event date should not be before the registration start date.'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        // Extract and convert date values.
        $start_date = $form_state->getValue('start_date');
        $end_date = $form_state->getValue('end_date');
        $event_date = $form_state->getValue('event_date');

        $data = [
            'event_name' => $form_state->getValue('event_name'),
            'category' => $form_state->getValue('category'),
            'start_date' => $start_date instanceof DrupalDateTime ? $start_date->getTimestamp() : 0,
            'end_date' => $end_date instanceof DrupalDateTime ? $end_date->getTimestamp() : 0,
            'event_date' => $event_date instanceof DrupalDateTime ? $event_date->getTimestamp() : 0,
        ];

        // Delegate to EventService for database insertion.
        $event_id = $this->eventService->createEvent($data);

        // Success message.
        $this->messenger()->addStatus($this->t('Event "@name" has been created successfully (ID: @id).', [
            '@name' => $data['event_name'],
            '@id' => $event_id,
        ]));

        // Redirect back to the same form to allow creating more events.
        $form_state->setRebuild(TRUE);
    }

}
