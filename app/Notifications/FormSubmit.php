<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FormSubmit extends Notification
{
    use Queueable;

    private $form_data;

    /**
     * Create a new notification instance.
     */
    public function __construct($form_data)
    {
        $this->form_data = $form_data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {

        $html = [];
        foreach ($this->form_data->data as $field) {
            $value = is_array($field->value) ? implode('<br>', $field->value) : $field->value;
            $html[] = '<strong>' . $field->label . ': </strong>' . $value . '<br>';
        }
        $html = implode($html);

        return (new MailMessage)
            ->subject($this->form_data->form->name . ' | ' . $this->form_data->form->project->name)
            ->greeting($this->form_data->form->name . ' | ' . $this->form_data->form->project->name)
            ->line($html);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
