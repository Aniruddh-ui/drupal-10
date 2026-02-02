<?php

declare(strict_types=1);

namespace Drupal\fossee_event\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Service for CRUD operations on the fossee_event_config table.
 *
 * Architectural Decision: This service encapsulates all database operations
 * for events, keeping the Form/Controller layer thin. Uses constructor
 * injection for the database connection to enable unit testing and avoid
 * \Drupal:: static calls.
 */
class EventService
{

    /**
     * The database connection.
     *
     * @var \Drupal\Core\Database\Connection
     */
    protected Connection $database;

    /**
     * The time service.
     *
     * @var \Drupal\Component\Datetime\TimeInterface
     */
    protected TimeInterface $time;

    /**
     * Constructs an EventService object.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   The database connection service.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     */
    public function __construct(Connection $database, TimeInterface $time)
    {
        $this->database = $database;
        $this->time = $time;
    }

    /**
     * Creates a new event in the database.
     *
     * @param array $data
     *   Associative array containing:
     *   - event_name: (string) The event display name.
     *   - category: (string) The event category.
     *   - start_date: (int) Unix timestamp for registration window open.
     *   - end_date: (int) Unix timestamp for registration window close.
     *   - event_date: (int) Unix timestamp for the actual event.
     *
     * @return int
     *   The ID of the newly created event.
     */
    public function createEvent(array $data): int
    {
        $now = $this->time->getRequestTime();

        $id = $this->database->insert('fossee_event_config')
            ->fields([
                'event_name' => $data['event_name'],
                'category' => $data['category'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'event_date' => $data['event_date'],
                'created' => $now,
                'changed' => $now,
            ])
            ->execute();

        return (int) $id;
    }

    /**
     * Retrieves all distinct categories from events.
     *
     * Used to populate the first AJAX dropdown in the registration form.
     *
     * @return array
     *   List of category strings.
     */
    public function getCategories(): array
    {
        $query = $this->database->select('fossee_event_config', 'e')
            ->fields('e', ['category'])
            ->distinct()
            ->orderBy('category', 'ASC');

        return $query->execute()->fetchCol();
    }

    /**
     * Retrieves event dates for a given category.
     *
     * Used for the second AJAX dropdown: Category -> Event Dates.
     *
     * @param string $category
     *   The category to filter by.
     *
     * @return array
     *   List of event_date timestamps.
     */
    public function getDatesByCategory(string $category): array
    {
        $query = $this->database->select('fossee_event_config', 'e')
            ->fields('e', ['event_date'])
            ->condition('category', $category)
            ->distinct()
            ->orderBy('event_date', 'ASC');

        return $query->execute()->fetchCol();
    }

    /**
     * Retrieves event names for a given category and date.
     *
     * Used for the third AJAX dropdown: Category + Date -> Event Names.
     *
     * @param string $category
     *   The category to filter by.
     * @param int $event_date
     *   The event date timestamp.
     *
     * @return array
     *   Associative array of event_id => event_name.
     */
    public function getEventsByDateAndCategory(string $category, int $event_date): array
    {
        $query = $this->database->select('fossee_event_config', 'e')
            ->fields('e', ['id', 'event_name'])
            ->condition('category', $category)
            ->condition('event_date', $event_date)
            ->orderBy('event_name', 'ASC');

        $results = $query->execute()->fetchAllKeyed();

        return $results;
    }

    /**
     * Retrieves all events currently within their registration window.
     *
     * An event is "active" if the current time is between start_date and end_date.
     *
     * @return array
     *   List of event records.
     */
    public function getActiveEvents(): array
    {
        $now = $this->time->getRequestTime();

        $query = $this->database->select('fossee_event_config', 'e')
            ->fields('e')
            ->condition('start_date', $now, '<=')
            ->condition('end_date', $now, '>=')
            ->orderBy('event_date', 'ASC');

        return $query->execute()->fetchAll();
    }

    /**
     * Loads a single event by ID.
     *
     * @param int $id
     *   The event ID.
     *
     * @return array|null
     *   The event record as an associative array, or NULL if not found.
     */
    public function loadEvent(int $id): ?array
    {
        $query = $this->database->select('fossee_event_config', 'e')
            ->fields('e')
            ->condition('id', $id);

        $result = $query->execute()->fetchAssoc();

        return $result ?: NULL;
    }

    /**
     * Retrieves all events for the admin listing.
     *
     * @return array
     *   List of all event records.
     */
    public function getAllEvents(): array
    {
        $query = $this->database->select('fossee_event_config', 'e')
            ->fields('e')
            ->orderBy('event_date', 'DESC');

        return $query->execute()->fetchAll();
    }

}
