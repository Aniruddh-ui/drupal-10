<?php

declare(strict_types=1);

namespace Drupal\fossee_event\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for FOSSEE Event module settings.
 *
 * Architectural Decision: This form uses ConfigFormBase because it manages
 * global module settings (admin email, enable/disable flags) stored in the
 * Config API. This is distinct from the EventForm which stores event data
 * in custom database tables.
 *
 * Settings managed:
 * - admin_notification_email: Email address to receive admin notifications.
 * - admin_notification_enabled: Whether to send admin notifications.
 * - user_confirmation_enabled: Whether to send user confirmation emails.
 */
class SettingsForm extends ConfigFormBase
{

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames(): array
    {
        return ['fossee_event.settings'];
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'fossee_event_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {
        $config = $this->config('fossee_event.settings');

        $form['email_settings'] = [
            '#type' => 'details',
            '#title' => $this->t('Email Notification Settings'),
            '#open' => TRUE,
        ];

        // Admin notification email address.
        $form['email_settings']['admin_notification_email'] = [
            '#type' => 'email',
            '#title' => $this->t('Admin Notification Email'),
            '#description' => $this->t('Email address to receive notifications when a new registration is submitted. Leave empty to disable admin notifications.'),
            '#default_value' => $config->get('admin_notification_email'),
            '#maxlength' => 255,
        ];

        // Enable/disable admin notifications.
        $form['email_settings']['admin_notification_enabled'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable Admin Notifications'),
            '#description' => $this->t('When enabled, the admin will receive an email for each new registration.'),
            '#default_value' => $config->get('admin_notification_enabled'),
        ];

        // Enable/disable user confirmation emails.
        $form['email_settings']['user_confirmation_enabled'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable User Confirmation Emails'),
            '#description' => $this->t('When enabled, users will receive a confirmation email after registration.'),
            '#default_value' => $config->get('user_confirmation_enabled'),
        ];

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state): void
    {
        parent::validateForm($form, $form_state);

        // If admin notifications are enabled, require an email address.
        $admin_enabled = $form_state->getValue('admin_notification_enabled');
        $admin_email = $form_state->getValue('admin_notification_email');

        if ($admin_enabled && empty($admin_email)) {
            $form_state->setErrorByName(
                'admin_notification_email',
                $this->t('An admin email address is required when admin notifications are enabled.')
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $this->config('fossee_event.settings')
            ->set('admin_notification_email', $form_state->getValue('admin_notification_email'))
            ->set('admin_notification_enabled', (bool) $form_state->getValue('admin_notification_enabled'))
            ->set('user_confirmation_enabled', (bool) $form_state->getValue('user_confirmation_enabled'))
            ->save();

        parent::submitForm($form, $form_state);
    }

}
