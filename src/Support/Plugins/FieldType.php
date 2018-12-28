<?php

namespace Kilvin\Support\Plugins;

use Kilvin\Facades\Cp;
use Kilvin\Plugins\Weblogs\Models\Entry;
use Illuminate\Database\Schema\Blueprint;

abstract class FieldType
{
    protected $field;
    protected $language;

    /**
     * Constructor
     *
     * Uses Laravel dependency injection, so put anything you want in the constructor attributes
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Set Field Details
     *
     * @param object The weblog_fields row
     * @return object
     */
    public function setField($field)
    {
        $this->field = $field;

        // Eventually we should do this in a Model with mutator
        if (!empty($this->field->settings) && is_string($this->field->settings)) {
            try {
                $this->field->settings = json_decode($this->field->settings, true);
            } catch (\Exception $e) {}
        }

        return $this;
    }

    /**
     * Set Language
     *
     * @param object
     * @return void
     */
    public function setLanguage($language)
    {
        $this->language = $language;
    }

    /**
     * Name of the Filter
     *
     * @return string
     */
    public function name()
    {
        // @todo - Translate!
        return 'Base FieldType';
    }

    /**
     * Column Type
     *
     * Essentially we send you the Blueprint object and you add whatever field type you want
     *
     * @link https://laravel.com/docs/5.5/migrations#columns
     * @param string $column_name What the column will be called in the weblog_field_data table
     * @param Illuminate\Database\Schema\Blueprint $table The table that is having the field added
     * @param null|array $settings The values of the settings for field
     * @param null|object $existing On edit, if changing field type, we send existing column details
     * @return void
     */
    public function columnType($column_name, Blueprint &$table, $settings = null, $existing = null)
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
        return $entry[$this->field->field_name];
    }

    /**
     * Settings Form HTML
     *
     * The HTML field you wish to display in the Edit Field Form
     *
     * @param array $settings The Settings for this field
     * @return string
     */
    public function settingsFormHtml(array $settings = [])
    {
        Cp::footerJavascript('');
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
        Cp::footerJavascript('');
        return '';
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
     * @return array Return validation rules and validation messages
     */
    public function publishFormValidation($which, $entry_data, $request_data, $submission_error)
    {
        $rules = [];
        $messages = [];

        // Example
        // $rules['fields.'.$this->field->field_handle][] = 'min:1';
        // $messages['fields.'.$this->field->field_handle.'.min'] = 'The '.$this->field->field_name.' field needs more content.';

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
        return $value;
    }

    // -------------------------------------------------------------------------
    //  Events!
    // -------------------------------------------------------------------------

    /**
     * Performs actions before an entry is saved.
     *
     * @param Kilvin\Plugins\Weblogs\Models\Entry $entry The entry that is about to be saved
     * @param bool $new   Is this a new entry?
     * @return bool Whether the entry should be saved
     */
    public function beforeEntrySave(Entry $entry, bool $new): bool
    {
        return true;
    }

    /**
     * Performs actions after the entry has been saved.
     *
     * @param Kilvin\Plugins\Weblogs\Models\Entry $entry The entry that was just saved
     * @param bool $new Whether the entry is new
     * @return void
     */
    public function afterEntrySave(Entry $entry, bool $new)
    {

    }

    /**
     * Performs actions before an entry is deleted.
     *
     * @param Kilvin\Plugins\Weblogs\Models\Entry $entry The entry that is about to be deleted
     * @return bool Whether the entry should be deleted
     */
    public function beforeEntryDelete(Entry $entry): bool
    {
        return true;
    }

    /**
     * Performs actions after the entry has been deleted.
     *
     * @param Kilvin\Plugins\Weblogs\Models\Entry $entry The entry that was just deleted
     * @return void
     */
    public function afterEntryDelete(Entry $entry)
    {

    }
}
