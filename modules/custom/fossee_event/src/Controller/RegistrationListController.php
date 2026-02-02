<?php

declare(strict_types=1);

namespace Drupal\fossee_event\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\fossee_event\Service\EventService;
use Drupal\fossee_event\Service\RegistrationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for the admin registration listing and CSV export.
 *
 * Architectural Decision: This controller uses dependency injection for
 * services and implements proper XSS prevention using render arrays.
 * Filtering is handled via query parameters for simplicity.
 */
class RegistrationListController extends ControllerBase
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
     * Constructs a RegistrationListController object.
     *
     * @param \Drupal\fossee_event\Service\EventService $eventService
     *   The event service.
     * @param \Drupal\fossee_event\Service\RegistrationService $registrationService
     *   The registration service.
     */
    public function __construct(
        EventService $eventService,
        RegistrationService $registrationService
    ) {
        $this->eventService = $eventService;
        $this->registrationService = $registrationService;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): static
    {
        return new static(
            $container->get('fossee_event.event_service'),
            $container->get('fossee_event.registration_service')
        );
    }

    /**
     * Displays the registration listing page with filters.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request.
     *
     * @return array
     *   A render array.
     */
    public function listRegistrations(Request $request): array
    {
        $build = [];

        // Get filter values from query parameters.
        $filter_event_date = $request->query->get('event_date', '');
        $filter_event_id = $request->query->get('event_id', '');

        // Build filters array.
        $filters = [];
        if (!empty($filter_event_date)) {
            $filters['event_date'] = (int) $filter_event_date;
        }
        if (!empty($filter_event_id)) {
            $filters['event_id'] = (int) $filter_event_id;
        }

        // Filter form.
        $build['filters'] = $this->buildFilterForm($filter_event_date, $filter_event_id);

        // Get total count.
        $total_count = $this->registrationService->countRegistrations($filters);

        // Counter display.
        $build['counter'] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['registration-counter']],
            'count' => [
                '#markup' => '<strong>' . $this->t('Total participants: @count', ['@count' => $total_count]) . '</strong>',
            ],
        ];

        // Export button.
        $export_url = Url::fromRoute('fossee_event.export_csv', [], [
            'query' => $request->query->all(),
        ]);
        $build['export'] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['export-button-wrapper']],
            'link' => [
                '#type' => 'link',
                '#title' => $this->t('Export to CSV'),
                '#url' => $export_url,
                '#attributes' => [
                    'class' => ['button', 'button--primary'],
                ],
            ],
        ];

        // Get registrations with pagination.
        $registrations = $this->registrationService->getRegistrations($filters);

        // Build the table.
        $build['table'] = $this->buildRegistrationsTable($registrations);

        return $build;
    }

    /**
     * Builds the filter form.
     *
     * @param string $selected_event_date
     *   The currently selected event date.
     * @param string $selected_event_id
     *   The currently selected event ID.
     *
     * @return array
     *   A render array for the filter form.
     */
    protected function buildFilterForm(string $selected_event_date, string $selected_event_id): array
    {
        // Get all events for the filter dropdowns.
        $events = $this->eventService->getAllEvents();

        // Build event date options.
        $date_options = ['' => $this->t('- All Dates -')];
        $event_options = ['' => $this->t('- All Events -')];
        $dates_seen = [];

        foreach ($events as $event) {
            // Date options (unique).
            if (!isset($dates_seen[$event->event_date])) {
                $date_options[$event->event_date] = date('Y-m-d', (int) $event->event_date);
                $dates_seen[$event->event_date] = TRUE;
            }
            // Event options.
            $event_options[$event->id] = htmlspecialchars($event->event_name, ENT_QUOTES, 'UTF-8');
        }

        return [
            '#type' => 'container',
            '#attributes' => ['class' => ['filter-form-wrapper']],
            'form' => [
                '#type' => 'inline_template',
                '#template' => '
          <form method="get" class="registration-filters">
            <div class="form-item">
              <label for="event_date">{{ date_label }}</label>
              <select name="event_date" id="event_date" onchange="this.form.submit()">
                {% for value, label in date_options %}
                  <option value="{{ value }}"{{ value == selected_date ? " selected" : "" }}>{{ label }}</option>
                {% endfor %}
              </select>
            </div>
            <div class="form-item">
              <label for="event_id">{{ event_label }}</label>
              <select name="event_id" id="event_id" onchange="this.form.submit()">
                {% for value, label in event_options %}
                  <option value="{{ value }}"{{ value == selected_event ? " selected" : "" }}>{{ label|raw }}</option>
                {% endfor %}
              </select>
            </div>
            <button type="submit" class="button">{{ filter_button }}</button>
            <a href="{{ clear_url }}" class="button">{{ clear_button }}</a>
          </form>
        ',
                '#context' => [
                    'date_label' => $this->t('Event Date'),
                    'event_label' => $this->t('Event Name'),
                    'date_options' => $date_options,
                    'event_options' => $event_options,
                    'selected_date' => $selected_event_date,
                    'selected_event' => $selected_event_id,
                    'filter_button' => $this->t('Filter'),
                    'clear_button' => $this->t('Clear'),
                    'clear_url' => Url::fromRoute('fossee_event.registration_list')->toString(),
                ],
            ],
        ];
    }

    /**
     * Builds the registrations table.
     *
     * @param array $registrations
     *   Array of registration records.
     *
     * @return array
     *   A render array for the table.
     */
    protected function buildRegistrationsTable(array $registrations): array
    {
        $header = [
            $this->t('ID'),
            $this->t('Name'),
            $this->t('Email'),
            $this->t('Department'),
            $this->t('Event'),
            $this->t('Event Date'),
            $this->t('Registered On'),
        ];

        $rows = [];
        foreach ($registrations as $registration) {
            // Load event name for display.
            $event = $this->eventService->loadEvent((int) $registration->event_id);
            $event_name = $event ? $event['event_name'] : 'Unknown';

            // XSS prevention: Using htmlspecialchars for all user-submitted data.
            $rows[] = [
                $registration->id,
                ['data' => ['#markup' => htmlspecialchars($registration->name, ENT_QUOTES, 'UTF-8')]],
                ['data' => ['#markup' => htmlspecialchars($registration->email, ENT_QUOTES, 'UTF-8')]],
                ['data' => ['#markup' => htmlspecialchars($registration->department, ENT_QUOTES, 'UTF-8')]],
                ['data' => ['#markup' => htmlspecialchars($event_name, ENT_QUOTES, 'UTF-8')]],
                date('Y-m-d', (int) $registration->event_date),
                date('Y-m-d H:i', (int) $registration->created),
            ];
        }

        return [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => $this->t('No registrations found.'),
            '#attributes' => ['class' => ['registrations-table']],
        ];
    }

    /**
     * Exports registrations to CSV.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *   A streamed CSV response.
     */
    public function exportCsv(Request $request): Response
    {
        // Get filter values from query parameters.
        $filter_event_date = $request->query->get('event_date', '');
        $filter_event_id = $request->query->get('event_id', '');

        // Build filters array.
        $filters = [];
        if (!empty($filter_event_date)) {
            $filters['event_date'] = (int) $filter_event_date;
        }
        if (!empty($filter_event_id)) {
            $filters['event_id'] = (int) $filter_event_id;
        }

        // Get registrations.
        $registrations = $this->registrationService->getRegistrations($filters);

        // Create streamed response for CSV.
        $response = new StreamedResponse(function () use ($registrations) {
            // Open output stream.
            $handle = fopen('php://output', 'w');

            // Add UTF-8 BOM for Excel compatibility.
            fwrite($handle, "\xEF\xBB\xBF");

            // Write header row.
            fputcsv($handle, [
                'ID',
                'Name',
                'Email',
                'Department',
                'Event Name',
                'Event Date',
                'Registered On',
            ]);

            // Write data rows.
            foreach ($registrations as $registration) {
                $event = $this->eventService->loadEvent((int) $registration->event_id);
                $event_name = $event ? $event['event_name'] : 'Unknown';

                fputcsv($handle, [
                    $registration->id,
                    $registration->name,
                    $registration->email,
                    $registration->department,
                    $event_name,
                    date('Y-m-d', (int) $registration->event_date),
                    date('Y-m-d H:i', (int) $registration->created),
                ]);
            }

            fclose($handle);
        });

        // Set response headers.
        $filename = 'registrations_' . date('Y-m-d_His') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

}
