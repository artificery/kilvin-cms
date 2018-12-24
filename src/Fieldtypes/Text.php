<?php

namespace Kilvin\FieldTypes;

use Kilvin\Facades\Cp;
use Kilvin\Plugins\Weblogs\Models\Entry;
use Illuminate\Database\Schema\Blueprint;
use Kilvin\Support\Plugins\FieldType;

class Text extends FieldType
{
    protected $field;

    /**
     * Name of the FieldType
     *
     * @return string
     */
    public function name()
    {
        return __('kilvin::admin.Text Input');
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
        $table->text($column_name)->nullable(true);
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
        return $value;
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
        $maxlength = (!empty($settings['text_max_length'])) ? $settings['text_max_length'] : 10;

        return
            '<table class="tableBorder">
                <tr>
                    <td class="tableHeading" colspan="2">'.__('kilvin::admin.Field Settings').'</td>
                </tr>
                <tr>
                    <td>
                        <label for="text_max_length">
                            '.__('kilvin::admin.field_max_length').'
                        </label>
                        <div class="littlePadding">
                            <input
                                type="text"
                                id="text_max_length"
                                name="settings[text_max_length]"
                                size="3"
                                value="'.$maxlength.'"
                            >
                        </div>
                     </td>
                 </tr>
             </table>';
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
        $rules['settings.text_max_length'] = 'nullable|integer|max:100';

        return $rules;
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
        $maxlength = (!empty($field->settings['text_max_length'])) ? ceil($field->settings['text_max_length']) : '';

        $data  = array_merge((array) $request_data, (array) $entry_data);
        $value = escapeAttribute($data['fields'][$this->field->field_handle] ?? '');

        return '<input
            style="width:100%""
            type="text"
            id="'.$this->field->field_handle.'"
            name="fields['.$this->field->field_handle.']"
            value="'.$value.'"
            maxlength="'.$maxlength.'">';
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
        return $value;
    }
}
