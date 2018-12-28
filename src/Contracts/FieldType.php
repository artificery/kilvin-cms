<?php

namespace Kilvin\Contracts;

use Illuminate\Database\Schema\Blueprint;

interface FieldType
{
    /**
     * Set Field Details
     *
     * @param object The weblog_fields row
     * @return object
     */
    public function setField($field);

    /**
     * Set Language
     *
     * @param object
     * @return void
     */
    public function setLanguage($language);

    /**
     * Name of the Filter
     *
     * @return string
     */
    public function name();

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
    public function columnType($column_name, Blueprint &$table, $settings = null, $existing = null);

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
    public function storedValue($value, $entry, $source);

    /**
     * Settings Form HTML
     *
     * The HTML field you wish to display in the Edit Field Form
     *
     * @param array $settings The Settings for this field
     * @return string
     */
    public function settingsFormHtml(array $settings = []);

    /**
     * Settings Form Validation Rules
     *
     * Rules for any Settings Form Fields submitted
     *
     * @param array $incoming The incoming data from the Request
     * @return array
     */
    public function settingsValidationRules($incoming = []);

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
    public function publishFormHtml($which, $entry_data, $request_data, $submission_error);

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
    public function publishFormValidation($which, $entry_data, $request_data, $submission_error);

    /**
     * Template Output
     *
     * What you output to the Template
     *
     * @param string|null $value The value of the field
     * @param array $entry All of the incoming entry data
     * @return mixed
     */
    public function templateOutput($value, $entry);
}
