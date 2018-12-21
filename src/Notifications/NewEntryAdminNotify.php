<?php

namespace Kilvin\Notifications;

use Illuminate\Support\Facades\DB;
use Kilvin\Facades\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class NewEntryAdminNotify extends Notification implements ShouldQueue
{
    use Queueable;

	/**
	* Weblog Entry ID
	*
	* @var integer
	*/
	public $entry_id;

    /**
    * Notify Address
    *
    * @var string
    */
    public $notify_address;

	/**
	* Create a notification instance.
	*
	* @param  string  $variable
	* @return void
	*/
	public function __construct($entry_id, $notify_address)
	{
		$this->entry_id = $entry_id;
        $this->notify_address = $notify_address;
	}

	/**
	* Get the notification's channels.
	*
	* @param  mixed  $notifiable
	* @return array|string
	*/
	public function via($notifiable)
	{
		return ['mail'];
	}

     /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $entry = DB::table('weblog_entries')
            ->join('weblog_entry_data', 'weblog_entry_data.weblog_entry_id', '=', 'weblog_entries.id')
            ->join('weblogs', 'weblogs.id', '=', 'weblog_entries.weblog_id')
            ->where('weblog_entries.id', $this->entry_id)
            ->first();

        if (!$entry) {
            return false;
        }

        $vars['notification_sender_email'] = Site::config('notification_sender_email');
        $vars['site_name']       = Site::config('site_name');
        $vars['site_url']        = Site::config('site_url');
        $vars['subject']         = __('kilvin::emails.new_weblog_entry_posted');

        $vars['greeting']        = 'Hello!';
        $vars['introLines'][]    = 'A new entry has been posted on '.Site::config('site_name');
        $vars['actionText']      = $entry->title;
        $vars['actionUrl']       = Site::config('cp_url').'/edit/edit-entry/entry_id='.$entry->entry_id;

        return (new MailMessage)
            ->from($vars['notification_sender_email'], $vars['site_name'])
            ->subject(__('kilvin::emails.new_weblog_entry_posted'))
            ->view('kilvin::cp.emails.base', $vars);
    }
}
