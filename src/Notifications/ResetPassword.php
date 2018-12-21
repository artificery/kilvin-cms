<?php

namespace Kilvin\Notifications;

use Kilvin\Facades\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\ResetPassword as IlluminateResetPassword;

class ResetPassword extends IlluminateResetPassword implements ShouldQueue
{
    use Queueable;

     /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {

        $subject = __('kilvin::emails.forgot_password_instructions');

        $vars['token']           = $this->token;

        $vars['notification_sender_email'] = Site::config('notification_sender_email');
        $vars['site_name']       = Site::config('site_name');
        $vars['site_url']        = Site::config('site_url');
        $vars['subject']         = $subject;

        // @todo - Localize
        $vars['greeting']        = 'Hello!';
        $vars['introLines'][]    = 'You are receiving this email because we received a password reset request for your account.';
        $vars['outroLines'][]    = 'If you did not request a password reset, no further action is required.';
        $vars['actionText']      = 'Reset Password';
        $vars['actionUrl']       = Site::config('cp_url')."?C=reset_password_form&token=".$this->token;

        return (new MailMessage)
            ->from($vars['notification_sender_email'], $vars['site_name'])
            ->subject($subject)
            ->view('kilvin::cp.emails.base', $vars); // Might change this to have action/error/success options
    }
}
