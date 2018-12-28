<?php

namespace Kilvin\FieldTypes;

use Kilvin\Facades\Cp;
use Illuminate\Support\Facades\DB;
use Kilvin\Plugins\Weblogs\Models\Entry;
use Illuminate\Database\Schema\Blueprint;
use Kilvin\Support\Plugins\FieldType;
use Kilvin\Contracts\FieldType as FieldTypeContract;

class Dropdown extends FieldType implements FieldTypeContract
{
    protected $field;

    /**
     * Name of the FieldType
     *
     * @return string
     */
    public function name()
    {
        return __('kilvin::admin.Dropdown');
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
     * Template Ouput
     *
     * That which is pushed out to the Template parser as final value
     *
     * @param string|null $value The value of the field
     * @param array $entry All of the incoming entry data
     * @param array $settings Settings for field
     * @return mixed Could be anything really, as long as Twig can use it
     */
    public function templateOutput($value, $entry, $settings)
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
        extract($settings);

        $populate_type = $settings['populate_type'] ?? 'manual';
        $list_items    = $settings['list_items'] ?? '';
        $weblog_field  = $settings['weblog_field'] ?? '';

        // ------------------------------------
        //  Create the "populate" radio options
        // ------------------------------------

        $typemenu = Cp::quickDiv(
            'default',
            '<label>'.
                Cp::input_radio(
                    'settings[Dropdown][populate_type]',
                    'manual',
                    ($populate_type == 'manual') ? true : false,
                    ' class="js-dropdown-populate"'
                ).
                ' '.
                __('kilvin::admin.Populate dropdown manually').
            '</label>');

        $typemenu .= Cp::quickDiv(
            'default',
            '<label>'.
                Cp::input_radio(
                    'settings[Dropdown][populate_type]',
                    'weblog',
                    ($populate_type == 'weblog') ? true : false,
                    ' class="js-dropdown-populate"'
                ).
                ' '.
                __('kilvin::admin.Populate dropdown from weblog field').
            '</label>');

        // ------------------------------------
        //  Populate Manually
        // ------------------------------------

        $typopts = '<div id="dropdown_populate_manual" style="display: none;">';

        $typopts .= Cp::quickDiv(
                'defaultBold',
                __('kilvin::admin.field_list_items')
            ).
            Cp::quickDiv(
                'default',
                __('kilvin::admin.field_list_instructions')
            ).
            Cp::input_textarea(
                'settings[Dropdown][list_items]',
                $list_items,
                10,
                'textarea',
                '400px',
                ' placeholder="value:Displayed Value"'
            );

        $typopts .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  Populate via an existing field
        // ------------------------------------

        $typopts .= '<div id="dropdown_populate_weblog" style="display: none;">';

        $query = DB::table('weblogs')
            ->orderBy('weblog_name', 'asc')
            ->select('id AS weblog_id', 'weblog_name', 'weblog_field_group_id')
            ->get();

        // Create the drop-down menu
        $typopts .= Cp::quickDiv('defaultBold', __('kilvin::admin.select_weblog_for_field'));
        $typopts .= "<select name='settings[Dropdown][weblog_field]' class='select'>".PHP_EOL;

        $pieces = explode(':', $weblog_field, 2);

        if (sizeof($pieces) != 2) {
            $pieces = ['', ''];
        }

        list($weblog_id, $field_handle) = $pieces;

        // Fetch the field names
        foreach ($query as $row) {
            $rez = DB::table('weblog_fields')
                ->where('weblog_field_group_id', $row->weblog_field_group_id)
                ->orderBy('field_name', 'asc')
                ->select('id AS field_id', 'field_handle', 'field_name')
                ->get();

            if ($rez->count() == 0) {
                continue;
            }

            $typopts .= '<optgroup label="'.escapeAttribute($row->weblog_name).'">';

            foreach ($rez as $frow)
            {
                $sel = ($weblog_id == $row->weblog_id && $field_handle == $frow->field_handle) ? 1 : 0;

                $typopts .= Cp::input_select_option(
                    $row->weblog_id.':'.$frow->field_handle,
                    $frow->field_name,
                    $sel);
            }

            $typopts .= '</optgroup>';
        }

        $typopts .= Cp::input_select_footer();
        $typopts .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  JavaScript
        // ------------------------------------

        $js = <<<EOT
    <script type="text/javascript">

         $( document ).ready(function() {
            $('input[name=settings\\\\[Dropdown\\\\]\\\\[populate_type\\\\]]').change(function(e) {
                e.preventDefault();

                var type = $('input[name=settings\\\\[Dropdown\\\\]\\\\[populate_type\\\\]]:checked').val();

                console.log(type);

                $('div[id^=dropdown_populate_]').css('display', 'none');

                $('#dropdown_populate_'+type).css('display', 'block');

            });

            $('input[name=settings\\\\[Dropdown\\\\]\\\\[populate_type\\\\]][value={$populate_type}]').prop("checked",true);;
            $('input[name=settings\\\\[Dropdown\\\\]\\\\[populate_type\\\\]]').trigger("change");
        });

    </script>
EOT;


        $r = $js;

        // ------------------------------------
        //  Table Containing Options
        // ------------------------------------

        $r .= '<table class="tableBorder">';
        $r .=
            '<tr>'.PHP_EOL.
                Cp::td('tableHeading', '', '2').
                    __('kilvin::admin.Field Settings').
                '</td>'.PHP_EOL.
            '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', $typemenu, '50%', 'top');
        $r .= Cp::tableCell('', $typopts, '50%');
        $r .= '</tr>'.PHP_EOL.'</table>';

        return $r;
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
        $rules['settings.Dropdown.populate_type'] = 'required|in:manual,weblog';
        $rules['settings.Dropdown.weblog_id'] = 'integer|exists:weblogs,id';
        $rules['settings.Dropdown.list_items'] = 'required_if:settings.Dropdown.populate_type,manual';
        $rules['settings.Dropdown.weblog_field'] = 'required_if:settings.Dropdown.populate_type,weblog';

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
        $field = $this->field;

        $data  = array_merge((array) $request_data, (array) $entry_data);
        $value = $data['fields'][$this->field->field_handle] ?? null;

        $r = Cp::input_select_header('fields['.$field->field_handle.']', '', '');

        if ($field->settings['populate_type'] == 'manual') {
            foreach (explode("\n", trim($field->settings['list_items'])) as $option) {
                $x = explode(':', trim($option));

                $v = $x[0];
                $n = $x[1] ?? $x[0];

                $selected = ($v == $value) ? 1 : '';

                $v = escapeAttribute($v);
                $r .= Cp::input_select_option($v, $n, $selected);
            }
        }

        // We need to pre-populate this menu from an another weblog custom field
        if ($field->settings['populate_type'] == 'weblog') {
            $pop_query = DB::table('weblog_entry_data')
                ->where('weblog_id', $field->settings['weblog_id'])
                ->select("field_".$field->settings['field_name'])
                ->get();

            $r .= Cp::input_select_option('', '--', '');

            if ($pop_query->count() > 0) {
                foreach ($pop_query as $prow) {
                    $selected = ($prow->{'field_'.$field->settings['field_name']} == $field_data) ? 1 : '';
                    $pretitle = substr($prow->{'field_'.$field->settings['field_name']}, 0, 110);
                    $pretitle = preg_replace("/\r\n|\r|\n|\t/", ' ', $pretitle);
                    $pretitle = escapeAttribute($pretitle);

                    $r .= Cp::input_select_option(
                        escapeAttribute(
                            $prow->{'field_'.$field->settings['field_name']}),
                        $pretitle,
                        $selected
                    );
                }
            }
        }

        $r .= Cp::input_select_footer();

        return $r;
    }
}
