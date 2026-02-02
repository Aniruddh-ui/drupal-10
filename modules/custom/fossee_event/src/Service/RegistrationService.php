<?php

declare(strict_types=1);

namespace Drupal\fossee_event\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Mail\MailManagerInterface;

/**
 * Service for handling user registration submissions.
 *
 * Architectural Decision: This service encapsulates registration logic,
 * duplicate checks, and email triggers. Uses constructor injection for
 * all dependencies to enable unit testing and avoid \Drupal:: static calls.
 */
class RegistrationService
{

    /**
     * The database connection.
     *
     * @var \Drupal\Core\Database\Connection
     */
    protected Connection $database;

    /**
     * The mail manager service.
     *
     * @var \Drupal\Core\Mail\MailManagerInterface
     */
    protected MailManagerInterface $mailManager;

    /**
     * The config factory service.
     *
     * @var \Drupal\Core\Config\ConfigFactoryInterface
     */
    protected ConfigFactoryInterface $configFactory;

    /**
     * The time service.
     *
     * @var \Drupal\Component\Datetime\TimeInterface
     */
    protected TimeInterface $time;

    /**
     * Constructs a RegistrationService object.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   The database connection service.
     * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
     *   The mail manager service for sending emails.
     * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
     *   The config factory for accessing module settings.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     */
    public function __construct(
        Connection $database,
        MailManagerInterface $mailManager,
        ConfigFactoryInterface $configFactory,
        TimeInterface $time
    ) {
        $this->database = $database;
        $this->mailManager = $mailManager;
        $this->configFactory = $configFactory;
        $this->time = $time;
    }

    /**
     * Saves a registration to the database.
     *
     * @param array $data
     *   Registration data containing:
     *   - event_id: (int) The event ID.
     *   - name: (string) Participant full name.
     *   - email: (string) Participant email.
     *   - department: (string) Participant department.
     *   - event_date: (int) The selected event date timestamp.
     *
     * @return int
     *   The ID of the saved registration.
     */
    public function saveRegistration(array $data): int
    {
        $now = $this->time->getRequestTime();

        $id = $this->database->insert('fossee_event_registration')
            ->fields([
                'event_id' => $data['event_id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'department' => $data['department'],
                'event_date' => $data['event_date'],
                'created' => $now,
            ])
            ->execute();

        return (int) $id;
    }

    /**
     * Checks if a duplicate registration exists.
     *
     * A duplicate is defined as the same email + event_date combination.
     * This provides application-layer validation complementing the DB constraint.
     *
     * @param string $email
     *   The participant email.
     * @param int $event_date
     *   The event date timestamp.
     *
     * @return bool
     *   TRUE if a duplicate exists, FALSE otherwise.
     */
    public function isDuplicate(string $email, int $event_date): bool
    {
        $query = $this->database->select('fossee_event_registration', 'r')
            ->fields('r', ['id'])
            ->condition('email', $email)
            ->condition('event_date', $event_date)
            ->range(0, 1);

        $result = $query->execute()->fetchField();

        return $result !== FALSE;
    }

    /**
     * Sends confirmation email to the user.
     *
     * @param array $registration
     *   The registration data including name and email.
     */
    public function sendUserConfirmation(array $registration): void
    {
        $config = $this->configFactory->get('fossee_event.settings');

        if (!$config->get('user_confirmation_enabled')) {
            return;
        }

        $this->mailManager->mail(
            'fossee_event',
            'user_confirmation',
            $registration['email'],
            'en',
            ['registration' => $registration],
            NULL,
            TRUE
        );
    }

    /**
     * Sends notification email to the admin.
     *
     * @param array $registration
     *   The registration data.
     */
    public function sendAdminNotification(array $registration): void
    {
        $config = $this->configFactory->get('fossee_event.settings');

        if (!$config->get('admin_notification_enabled')) {
            return;
        }

        $admin_email = $config->get('admin_notification_email');
        if (empty($admin_email)) {
            return;
        }

        $this->mailManager->mail(
            'fossee_event',
            'admin_notification',
            $admin_email,
            'en',
            ['registration' => $registration],
            NULL,
            TRUE
        );
    }

    /**
     * Retrieves registrations with optional filters.
     *
     * @param array $filters
     *   Optional filters:
     *   - event_date: (int) Filter by event date.
     *   - event_id: (int) Filter by event ID.
     *
     * @return array
     *   List of registration records.
     */
    public function getRegistrations(array $filters = []): array
    {
        $query = $this->database->select('fossee_event_registration', 'r')
            ->fields('r')
            ->orderBy('created', 'DESC');

        if (!empty($filters['event_date'])) {
            $query->condition('event_date', $filters['event_date']);
        }

        if (!empty($filters['event_id'])) {
            $query->condition('event_id', $filters['event_id']);
        }

        return $query->execute()->fetchAll();
    }

    /**
     * Counts total registrations with optional filters.
     *
     * @param array $filters
     *   Optional filters:
     *   - event_date: (int) Filter by event date.
     *   - event_id: (int) Filter by event ID.
     *
     * @return int
     *   Total count of matching registrations.
     */
    public function countRegistrations(array $filters = []): int
    {
        $query = $this->database->select('fossee_event_registration', 'r');
        $query->addExpression('COUNT(*)', 'count');

        if (!empty($filters['event_date'])) {
            $query->condition('event_date', $filters['event_date']);
        }

        if (!empty($filters['event_id'])) {
            $query->condition('event_id', $filters['event_id']);
        }

        return (int) $query->execute()->fetchField();
    }

}
