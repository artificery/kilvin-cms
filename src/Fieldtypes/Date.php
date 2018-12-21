<?php

namespace Kilvin\FieldTypes;

use Carbon\Carbon;
use Kilvin\Facades\Site;
use Kilvin\Facades\Cp;
use Kilvin\Core\Localize;
use Kilvin\Plugins\Weblogs\Models\Entry;
use Illuminate\Database\Schema\Blueprint;
use Kilvin\Support\Plugins\FieldType;
use Illuminate\Validation\ValidationException;

class Date extends FieldType
{
    protected $field;

    /**
     * Name of the FieldType
     *
     * @return string
     */
    public function name()
    {
        return __('kilvin::admin.Date');
    }

    /**
     * Column Type
     *
     * Essentially we send you the Blueprint object and you add whatever field type you want
     *
     * @link https://laravel.com/docs/5.5/migrations#columns
     * @param string $column_name What the column will be called in the weblog_field_data table
     * @param Illuminate\Database\Schema\Blueprint $table The table that is having the field added
     * @param null|object $existing On edit, if changing field type, we send existing column details
     * @return void
     */
    public function columnType($column_name, Blueprint &$table, $existing = null)
    {
        $table->timestamp($column_name)->nullable(true);
    }

    /**
     * Field Ouput
     *
     * That which is pushed out to the Template parser as final value
     *
     * @param string|null $value The value of the field
     * @param array $entry All of the incoming entry data
     * @param string $source db/post
     * @return mixed Could be anything really, as long as Twig can use it
     */
    public function storedValue($value, $entry, $source)
    {
        if (empty($value)) {
            return null;
        }

        $custom_date = Localize::humanReadableToUtcCarbon($value);

        // Localize::humanReadableToUtcCarbon() returns either a
        // valid Carbon object or a verbose error
        if ( ! $custom_date instanceof Carbon) {
            if ($custom_date !== false) {
                throw new ValidationException($custom_date.' ('.$this->field->field_name.')');
            }

            throw new ValidationException(__('kilvin::publish.invalid_date_formatting'));
        }

        return $custom_date;
    }

    /**
     * Settings Form HTML
     *
     * The HTML fields you wish to display in the Edit Field Form
     *
     * @param array $settings The Settings for this field
     * @return string
     */
    public function settingsFormHtml(array $settings = [])
    {
        return '';
    }

    /**
     * Settings Form Validation Rules
     *
     * Rules for any Settings Form Fields submitted
     *
     * @param array $incoming The incoming data from the Request
     * @return array
     */
    public function settingsValidationRules($incoming = [])
    {
        return [];
    }

    /**
     * Publish Form HTML
     *
     * The HTML displayed in the Publish form for this field.
     * I'd suggest using views to build this, but who am I to tell you what's right?
     *
     * @param string $which new/edit
     * @param array|null $entry_data The Entry's current data in database, if any
     * @param array|null $request_data If entry was submitted and there were errors, this is data submitted
     * @param string $submission_error
     * @return string
     */
    public function publishFormHtml($which, $entry_data, $request_data, $submission_error)
    {
        if (empty($value)) {
            $value = '';
        }

        $r = '';
        $custom_date_string = '';
        $custom_date = '';
        $cal_date = '';

        if (!empty($request_data['fields']) && !empty($request_data['fields'][$this->field->field_handle])) {
            $value = $request_data['fields'][$this->field->field_handle];
            $custom_date = (empty($value)) ? '' : Localize::humanReadableToUtcCarbon($value);
        } else {
            $value = $entry_data['fields'][$this->field->field_handle] ?? '';
            $custom_date = (empty($value)) ? '' : Carbon::parse($value);
        }

        if (!empty($custom_date) && $custom_date instanceof Carbon) {
            $date_object     = (empty($custom_date)) ? Carbon::now() : $custom_date->copy();
            $date_object->tz = Site::config('site_timezone');
            $cal_date        = $date_object->timestamp * 1000;

            $custom_date_string = Localize::createHumanReadableDateTime($date_object);
        }

        // ------------------------------------
        //  JavaScript Calendar
        // ------------------------------------

        $field_id = 'fields_'.$this->field->field_handle;

        $cal_img =
            '<a href="#" class="toggle-element" data-toggle="calendar_'.$field_id.'">
                <span style="display:inline-block; height:25px; width:25px; vertical-align:top;">
                    '.Cp::calendarImage().'
                </span>
            </a>';

        $r .= Cp::input_text(
            'fields['.$this->field->field_handle.']',
            $custom_date_string,
            '22',
            '22',
            'input',
            '170px',
            'id="'.$field_id.'" onkeyup="update_calendar(\'fields_'.$this->field->field_handle.'\', this.value);" '
        ).
        $cal_img;

        $r .= '<div id="calendar_'.$field_id.'" style="display:none;margin:4px 0 0 0;padding:0;">';

        $xmark = ($custom_date_string == '') ? 'false' : 'true';
        $r .= PHP_EOL.'<script type="text/javascript">

                var '.$field_id .' = new calendar(
                                        "'.$field_id.'",
                                        new Date('.$cal_date.'),
                                        '.$xmark.'
                                        );

                document.write('.$field_id.'.write());
                </script>'.PHP_EOL;

        $r .= '</div>';

        $r .= Cp::div('littlePadding');
        $r .= '<a href="javascript:void(0);" onclick="set_to_now(\''.$field_id.'\')" >'.
        __('kilvin::publish.today').
        '</a>'.NBS.'|'.NBS;
        $r .= '<a href="javascript:void(0);" onclick="clear_field(\''.$field_id.'\');" >'.__('kilvin::cp.clear').'</a>';
        $r .= '</div>'.PHP_EOL;

        return $r;
    }

    /**
     * Publish Form Validation
     *
     * The validation rules performed on submission
     *
     * @param string $which new/edit
     * @param array|null $entry_data The Entry's current data in database, if any
     * @param array|null $request_data If entry was submitted and there were errors, this is data submitted
     * @param string $submission_error
     * @return array
     */
    public function publishFormValidation($which, $entry_data, $request_data, $submission_error)
    {
        $rules = [];
        $messages = [];

        $rules['fields.'.$this->field->field_handle][] = 'date';
        $messages['fields.'.$this->field->field_handle.'.date'] = 'The '.$this->field->field_name.' did not have a valid date format.';

        return [$rules, $messages];
    }

    /**
     * Template Output
     *
     * What you output to the Template
     *
     * @param string|null $value The value of the field
     * @param array $entry All of the incoming entry data
     * @return mixed
     */
    public function templateOutput($value, $entry)
    {
        return (string) $value;
    }
}
