<?php

namespace Kilvin\Core;

use Kilvin\Facades\Site;
use Carbon\Carbon;

class Localize
{
    // --------------------------------------------------------------------

    /**
    * Create a Carbon object for us
     *
     * @param string|integer|object $now
     * @param boolean $localize
     * @param boolean $seconds
     * @return Carbon\Carbon
     */
    public static function createCarbonObject($time, $timezone = 'utc')
    {
        if (empty($time)) {
            return Carbon::now();
        }

        if ($time instanceof Carbon) {
            // We assume it has right timezone already
            $object = $time;
        } elseif(preg_match('/^[0-9]+$/', $time)) {
            $object = Carbon::createFromTimestamp($time);
            $object->tz = $timezone;
        } else {
            $object = static::humanReadableToUtcCarbon($time, $timezone);
        }

        return $object;
    }

    // --------------------------------------------------------------------

    /**
     *  Create a Human Readable Date Time for the UTC time
     *
     * @param string|integer|object $now
     * @param boolean $localize
     * @param boolean $seconds
     * @return Carbon\Carbon
     */
    public static function createHumanReadableDateTime($now = null, $seconds = false, $format = null)
    {
        $date_format =
            (Session::userdata('date_format') != '') ?
            Session::userdata('date_format') :
            Site::config('date_format');

        $time_format =
            (Session::userdata('time_format') != '') ?
            Session::userdata('time_format') :
            Site::config('time_format');

        // Should come in as UTC
        $object = static::createCarbonObject($now, 'utc');

        $object->timezone = Site::config('site_timezone');

        if (!is_null($format)) {
            $string = $object->format($format);
        } else {
            $string = $object->format($date_format.' '.$time_format.':s');
        }

        if ($seconds === false) {
            $string = preg_replace('/:[0-9]{2}$/', '', $string);
        }

        return $string;
    }

    // -------------------------------------------------------

    /**
     * Convert Human Readable date to UTC Carbon object
     *
     * @param string $datestr
     * @return Carbon\Carbon
     */
    public static function humanReadableToUtcCarbon($datestr = '', $timezone = null)
    {
        if (empty($datestr)) {
            return false;
        }

        // Removes duplicate hyphens
        $datestr = preg_replace("/\040+/", "\040", trim($datestr));

        // Right now, Kilvin only supports one date format and either 24-hour or 12-hour time
        // So, this is a really simple validation preg_match for that
        if ( ! preg_match('/^[0-9]{2,4}\-[0-9]{1,2}\-[0-9]{1,2}\s[0-9]{1,2}:[0-9]{1,2}(?::[0-9]{1,2})?(?:\s[AP]M)?$/i', $datestr)) {
            return __('kilvin::publish.invalid_date_formatting');
        }

        // If no timezone, we assume it is coming from a form and it is in the site's timezone
        if (empty($timezone)) {
            $timezone = Site::config('site_timezone');
        }

        $date = Carbon::parse($datestr, $timezone);

        // Convert to UTC
        $date->tz = 'utc';

        return $date;
    }

    // -------------------------------------------------------

    /**
     * Create a Relative Date
     *
     * @example "10 days 14 hours 36 minutes 47 seconds"
     * @param string $date
     * @return string
     */
    public static function relativeDate($date = '')
    {
        $parsed = Carbon::parse($date);

        $seconds = Carbon::now()->timestamp - $parsed->timestamp;

        // Negative number may be passed in load-balanced environments when
        // servers are not in perfect sync with one another
        $seconds = abs($seconds);

        if (empty($seconds)) {
            $seconds = 1;
        }

        $str = '';

        $years = floor($seconds / 31536000);

        if ($years > 0) {
            $str .= $years.' '.__(($years  > 1) ? 'core.years' : 'core.year').', ';
        }

        $seconds -= $years * 31536000;

        $months = floor($seconds / 2628000);

        if ($years > 0 || $months > 0) {
            if ($months > 0) {
                $str .= $months.' '.__(($months    > 1) ? 'core.months' : 'core.month').', ';
            }

            $seconds -= $months * 2628000;
        }

        $weeks = floor($seconds / 604800);

        if ($years > 0 || $months > 0 || $weeks > 0) {
            if ($weeks > 0) {
                $str .= $weeks.' '.__(($weeks > 1) ? 'core.weeks' : 'core.week').', ';
            }

            $seconds -= $weeks * 604800;
        }

        $days = floor($seconds / 86400);

        if ($months > 0 || $weeks > 0 || $days > 0) {
            if ($days > 0) {
                $str .= $days.' '.__(($days > 1) ? 'core.days' : 'core.day').', ';
            }

            $seconds -= $days * 86400;
        }

        $hours = floor($seconds / 3600);

        if ($days > 0 || $hours > 0) {
            if ($hours > 0) {
                $str .= $hours.' '.__(($hours > 1) ? 'core.hours' : 'core.hour').', ';
            }

            $seconds -= $hours * 3600;
        }

        $minutes = floor($seconds / 60);

        if ($days > 0 || $hours > 0 || $minutes > 0) {
            if ($minutes > 0) {
                $str .= $minutes.' '.__(($minutes  > 1) ? 'core.minutes' : 'core.minute').', ';
            }

            $seconds -= $minutes * 60;
        }

        if ($str == '') {
            $str .= $seconds.' '.__(($seconds  > 1) ? 'core.seconds' : 'core.second').', ';
        }

        $str = substr(trim($str), 0, -1);

        return $str;
    }

    // -------------------------------------------------------

    /**
     * How many Days Ago was this date?
     *
     * @param string $date
     * @return integer
     */
    public static function daysAgo($date = '')
    {
        $parsed = Carbon::parse($date);

        $seconds = Carbon::now()->timestamp - $parsed->timestamp;

        // Negative number may be passed in load-balanced environments when
        // servers are not in perfect sync with one another
        $seconds = abs($seconds);

        if (empty($seconds)) {
            $seconds = 1;
        }

        return floor($seconds / (60 * 60 * 24));
    }

    // ------------------------------------
    //  Convert timestamp codes
    // ------------------------------------

    public static function format($which = '', $time = '', $localize = true)
    {
        if ($which == '') {
            return;
        }

        if ($time == 'now') {
            $time = Carbon::now();
        }

        if (empty($time) OR $time === '0000-00-00 00:00:00') {
            return $time;
        }

        $which = str_replace('%', '', $which);

        $object = static::createCarbonObject($time);

        if ($localize === true) {
            $object->tz = Site::config('site_timezone');
        }

        return $object->format($which);
    }

    // ------------------------------------
    //  Create timezone localization pull-down menu
    // ------------------------------------

    public static function timezoneMenu($default = '')
    {
        $r  = "<div class='default'>";
        $r .= "<select name='site_timezone' class='select'>";

        foreach (timezone_abbreviations_list() as $key => $val)
        {
            foreach($val as $val2) {
                if (isset($val2['timezone_id'])) {
                    $selected = ($default == $val2['timezone_id']) ? " selected='selected'" : '';
                    $r .= "<option value='{$val2['timezone_id']}'{$selected}>".$val2['timezone_id']."</option>\n";
                }
            }
        }

        $r .= "</select>";
        $r .= "</div>";

        return $r;
    }

    // ------------------------------------
    //  Timezones
    // ------------------------------------

    public static function zones()
    {
        return timezone_abbreviations_list();
    }

}