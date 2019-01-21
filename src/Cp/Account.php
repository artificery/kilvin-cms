<?php

namespace Kilvin\Cp;

use Kilvin\Facades\Cp;
use Illuminate\Support\Facades\DB;
use Kilvin\Facades\Site;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;
use Carbon\Carbon;
use Kilvin\Core\ValidateAccount;
use Kilvin\Core\Session;
use Kilvin\Models\Member;
use Kilvin\Core\Localize;

class Account
{
    protected $screen_name = '';

   /**
    * Constructor
    *
    * @return void
    */
    public function __construct()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Fetch screen name
        // ------------------------------------

        $query = DB::table('members')
            ->where('id', $id)
            ->select('screen_name')
            ->first();

        if (!$query) {
            return Cp::unauthorizedAccess();
        }

        $this->screen_name = $query->screen_name;
    }

   /**
    * Request Handler
    *
    * @return mixed
    */
    public function run()
    {
        if (!Cp::segment(2) || preg_match('/^id=[0-9]+$/', Cp::segment(2))) {
            return $this->accountWrapper();
        }

        if (method_exists($this, camel_case(Cp::segment(2)))) {
            return $this->{camel_case(Cp::segment(2))}();
        }

        abort(404);
    }

   /**
    * Validate user and get the member ID number
    *
    * @return bool|integer
    */
    public function fetchAuthId()
    {
        if (Request::filled('id')) {
            $id = Request::input('id');
        } elseif (Cp::pathVar('id')) {
            $id = Cp::pathVar('id');
        } else {
            $id = Session::userdata('member_id');
        }

        if ( ! is_numeric($id)) {
            return false;
        }

        if ($id != Session::userdata('member_id')) {
            if ( ! Session::access('can_admin_members')) {
                return false;
            }

            // Only Admins can view Admin profiles
            $member_group_id = DB::table('members')
                ->where('id', $id)
                ->value('member_group_id');

            if (!$member_group_id) {
                return false;
            }

            if ($member_group_id == 1 AND Session::userdata('member_group_id') != 1) {
                return false;
            }
        }

        return $id;
    }

   /**
    * Left Side Menu for Account page
    *
    * @param string $path
    * @param string $text
    * @return string
    */
    public function nav($path = '', $text = '')
    {
        if ($path == '') {
            return false;
        }

        if ($text == '') {
            return false;
        }

        return Cp::quickDiv('navPad', Cp::anchor('account/'.$path, __('kilvin::'.$text)));
    }

   /**
    * The "Account" section wrapper for pages
    *
    * @param string $patitleth
    * @param string $crumb
    * @param string $content
    * @return string
    */
    public function accountWrapper($title = '', $crumb = '', $content = '')
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        // -------------------------------------------
        //  Set Title and Crumb
        // -------------------------------------------

        if ($title == '') {
            $title = __('kilvin::cp.my_account');
        }

        if ($crumb == '') {
            if ($id != Session::userdata('member_id')) {
                $crumb = __('kilvin::account.user_account');
            } else {
                $crumb = __('kilvin::cp.my_account');
            }
        }

        if ($content == '') {
            $content .= $this->homepage();
        }

        Cp::$title = $title;
        Cp::$crumb = $crumb;

        // -------------------------------------------
        //  Build Page
        // -------------------------------------------

        Cp::$body  .=  Cp::table('', '0', '', '100%').
        				'<tr class="no-background">'.
                        Cp::td('', '240px', '', '', 'top');

        Cp::$body  .=  Cp::quickDiv('tableHeading', __('kilvin::account.current_member').'&nbsp;'.$this->screen_name);

        Cp::$body .= '<div id="menu_profile_b">';

        Cp::$body .= Cp::div();
        Cp::$body .= "<div class='tableHeadingAlt pointer' id='prof2'>";
        Cp::$body .= __('kilvin::account.personal_settings');
        Cp::$body .= '</div>'.PHP_EOL;
        Cp::$body  .=  Cp::div('profileMenuInner').
            $this->nav('email-password-form/id='.$id, 'account.email_and_password').
            $this->nav('edit-profile/id='.$id, 'account.edit_profile').
            $this->nav('edit-photo/id='.$id, 'members.edit_photo').
            $this->nav('notification-settings/id='.$id, 'account.notification_settings').
            $this->nav('notepad/id='.$id, 'account.notepad').
        '</div>'.PHP_EOL;
        Cp::$body  .= '</div>'.PHP_EOL;
        Cp::$body  .= '</div>'.PHP_EOL;



        Cp::$body .= Cp::div();
        Cp::$body .= "<div class='tableHeadingAlt pointer' id='mcp2'>";
        Cp::$body .= __('kilvin::account.customize_cp');
        Cp::$body .= '</div>'.PHP_EOL;
        Cp::$body .= Cp::div('profileMenuInner');
        Cp::$body .= $this->nav('homepage-display/id='.$id, 'account.cp_homepage');
        Cp::$body .= $this->nav('choose-cp-theme/id='.$id, 'account.cp_theme');
        Cp::$body .= $this->nav('quicklinks/id='.$id, 'account.quick_links');
        Cp::$body .= $this->nav('tab-manager/id='.$id, 'account.tab_manager');
        Cp::$body .= '</div>'.PHP_EOL;
        Cp::$body  .= '</div>'.PHP_EOL;
        Cp::$body  .= '</div>'.PHP_EOL;


        if (Session::access('can_admin_members')) {
            Cp::$body .= '<div id="menu_ad_b">';
            Cp::$body .= Cp::div();
            Cp::$body .= "<div class='tableHeadingAlt pointer' id='adx2'>";
            Cp::$body .= __('kilvin::account.administrative_options');
            Cp::$body .= '</div>'.PHP_EOL;
            Cp::$body .= Cp::div('profileMenuInner');
            Cp::$body .= $this->nav('administration/id='.$id, 'account.member_preferences');

            if (
                $id != Session::userdata('member_id') &&
                Site::config('req_mbr_activation') == 'email' &&
                Session::access('can_admin_members')
            ) {
                $member_group_id = DB::table('members')
                    ->where('id', $id)
                    ->value('member_group_id');

                // @todo - Need to Rebuild this functionality
                if ($member_group_id == '4') {
                    Cp::$body .= Cp::quickDiv(
                        'navPad',
                        Cp::anchor(
                            'members/resend-activation-email/mid='.$id,
                            __('kilvin::account.resend_activation_email')
                        )
                    );
                }
            }

            if (Session::userdata('member_group_id') == 1 && $id != Session::userdata('member_id')) {
                Cp::$body .= Cp::quickDiv(
                    'navPad',
                    Cp::anchor(
                        'members/login-as-member/mid='.$id,
                        __('kilvin::account.login_as_member')
                    )
                );
            }

            if (Session::access('can_admin_members')) {
                Cp::$body .= Cp::quickDiv(
                    'navPad',
                    Cp::anchor(
                        'members/delete-member-confirm/mid='.$id,
                        __('kilvin::account.delete_member')
                    )
                );
            }

            Cp::$body .= '</div>'.PHP_EOL;
            Cp::$body .= '</div>'.PHP_EOL;
            Cp::$body .= '</div>'.PHP_EOL;
        }

        Cp::$body .=   '</div>'.PHP_EOL;
        Cp::$body .=   '</div>'.PHP_EOL;

        Cp::$body  .=  '</td>'.PHP_EOL.
                        Cp::td('', '8px', '', '', 'top').'&nbsp;'.'</td>'.PHP_EOL.
                        Cp::td('', '', '', '', 'top').
                        $content.
                        '</td>'.PHP_EOL.
                        '</tr>'.PHP_EOL.
                        '</table>'.PHP_EOL;
    }

   /**
    * The Edit Profile form
    *
    * @return string
    */
    public function homepage()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        $query = DB::table('members')
            ->where('id', $id)
            ->first();

        if (!$query) {
            return false;
        }

        foreach ($query as $key => $val) {
            $$key = $val;
        }

        $i = 0;

        $r  = Cp::table('tableBorderNoTop', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::th('', '', '2').__('kilvin::account.member_stats').'&nbsp;'.$this->screen_name.'</th>'.PHP_EOL;
              '</tr>'.PHP_EOL;

        $fields = [
            'cp.email'                  => Cp::mailto($email),
            'account.join_date'         => Localize::createHumanReadableDateTime($join_date),
            'account.total_entries'     => $total_entries,
            'account.last_entry_date'   =>
                ($last_entry_date == 0 OR $last_entry_date == '') ?
                '--' :
                Localize::createHumanReadableDateTime($last_entry_date),
            'account.user_ip_address'   => $ip_address
        ];

        foreach ($fields as $key => $val) {
            $r .= '<tr>'.PHP_EOL;
            $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::'.$key)), '50%');
            $r .= Cp::tableCell('', $val, '50%');
            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        return $r;
    }

   /**
    * Edit Profile form
    *
    * @return  string
    */
    public function editProfile()
    {
        $screen_name    = '';
        $email          = '';
        $url            = '';

        if (false === ($id = $this->fetchAuthId()))
        {
            return Cp::unauthorizedAccess();
        }

        $title = __('kilvin::account.edit_profile');

        // ------------------------------------
        //  Fetch profile data
        // ------------------------------------

        $query = DB::table('members')
            ->where('id', $id)
            ->first();

        foreach ($query as $key => $val)
        {
            $$key = $val;
        }

        // ------------------------------------
        //  Declare form
        // ------------------------------------

        $r  = Cp::formOpen(['action' => 'account/update-profile']).
              Cp::input_hidden('id', $id);

        // ------------------------------------
        //  Birthday Year Menu
        // ------------------------------------

        $bd  = Cp::input_select_header('bday_y');
        $bd .= Cp::input_select_option('', __('kilvin::account.year'), ($bday_y == '') ? 1 : '');

        for ($i = date('Y', Carbon::now()->timestamp); $i > 1904; $i--)
        {
          $bd .= Cp::input_select_option($i, $i, ($bday_y == $i) ? 1 : '');
        }

        $bd .= Cp::input_select_footer();

        // ------------------------------------
        //  Birthday Month Menu
        // ------------------------------------

        $months = [
            '01' => 'January',
            '02' => 'February',
            '03' => 'March',
            '04' => 'April',
            '05' => 'May',
            '06' => 'June',
            '07' => 'July',
            '08' => 'August',
            '09' => 'September',
            '10' => 'October',
            '11' => 'November',
            '12' => 'December'
        ];

        $bd .= Cp::input_select_header('bday_m');
        $bd .= Cp::input_select_option('', __('kilvin::account.month'), ($bday_m == '') ? 1 : '');

        foreach ($months as $month_num => $month_text)
        {
          if (strlen($i) == 1) {
             $i = '0'.$i;
          }

          $bd .= Cp::input_select_option($month_num, __('kilvin::core.'.$month_text), ($bday_m == $month_num) ? 1 : '');
        }

        $bd .= Cp::input_select_footer();

        // ------------------------------------
        //  Birthday Day Menu
        // ------------------------------------

        $bd .= Cp::input_select_header('bday_d');
        $bd .= Cp::input_select_option('', __('kilvin::account.day'), ($bday_d == '') ? 1 : '');

        for ($i = 31; $i >= 1; $i--) {
            $bd .= Cp::input_select_option($i, $i, ($bday_d == $i) ? 1 : '');
        }

        $bd .= Cp::input_select_footer();

        // ------------------------------------
        //  Build Page Output
        // ------------------------------------

        $i = 0;

        if (Cp::pathVar('U')) {
            $r .= Cp::quickDiv('success-message', __('kilvin::account.profile_updated'));
        }

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::th('', '', '2');

        $r .= __('kilvin::account.profile_form');

        $r .= '</th>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::account.birthday')), '25%');
        $r .= Cp::tableCell('', $bd, '75%');
        $r .= '</tr>'.PHP_EOL;

        if ($url == '') {
            $url = 'https://';
        }

        $fields = [
            'url'           => array('i', '75'),
            'location'      => array('i', '50'),
            'occupation'    => array('i', '80'),
            'interests'     => array('i', '75'),
            'bio'           => array('t', '12')
        ];

        foreach ($fields as $key => $val) {
            $align = ($val[0] == 'i') ? '' : 'top';

            $r .= '<tr>'.PHP_EOL;
            $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::account.'.$key)), '', $align);

            if ($val[0] == 'i')
            {
                $r .= Cp::tableCell('', Cp::input_text($key, $$key, '40', $val[1], 'input', '100%'));
            }
            elseif ($val[0] == 't')
            {
                $r .= Cp::tableCell('', Cp::input_textarea($key, $$key, $val[1], 'textarea', '100%'));
            }
            $r .= '</tr>'.PHP_EOL;
        }

        // ------------------------------------
        //  Extended profile fields
        // ------------------------------------

        $query = DB::table('member_fields')
            ->orderBy('field_order');

        if (Session::userdata('member_group_id') != 1) {
            $query->where('is_field_public', 1);
        }

        $query = $query->get();

        if ($query->count() > 0) {
            $result = DB::table('member_data')
                ->where('member_id', $id)
                ->first();

            if ($result) {
                foreach ($result as $key => $val) {
                    $$key = $val;
                }
            }

            foreach ($query as $row)
            {
                $field_data = (
                    !isset( $result->{'m_field_'.$row->field_handle} ))
                    ? ''
                    : $result->{'m_field_'.$row->field_handle};

                $width = '100%';

                $required  = ($row->is_field_required == 1) ? '&nbsp;'.Cp::required() : '';

                // Textarea fieled types

                if ($row->field_type == 'textarea') {
                    $rows = ( ! isset($row->textarea_num_rows)) ? '10' : $row->textarea_num_rows;

                    $r .= '<tr>'.PHP_EOL;
                    $r .= Cp::tableCell(
                        '',
                        Cp::quickDiv('defaultBold', $row->field_name.$required).
                        Cp::quickDiv('default', $row->field_description),
                        '',
                        'top'
                    );
                    $r .= Cp::tableCell('', Cp::input_textarea('m_field_'.$row->field_handle, $field_data, $rows, 'textarea', $width));
                    $r .= '</tr>'.PHP_EOL;
                } else {
                    // Text input fields

                    if ($row->field_type == 'text')
                    {

                        $r .= '<tr>'.PHP_EOL;
                        $r .= Cp::tableCell(
                            '',
                            Cp::quickDiv('defaultBold', $row->field_name.$required).
                            Cp::quickDiv('default', $row->field_description.$required)
                        );
                        $r .= Cp::tableCell('', Cp::input_text('m_field_'.$row->field_handle, $field_data, '20', '100', 'input', $width));
                        $r .= '</tr>'.PHP_EOL;
                    }

                    // Drop-down lists

                    elseif ($row->field_type == 'select')
                    {
                        $d = Cp::input_select_header('m_field_'.$row->field_handle);

                        foreach (explode("\n", trim($row->field_list_items)) as $v)
                        {
                            $v = trim($v);

                            $selected = ($field_data == $v) ? 1 : '';

                            $d .= Cp::input_select_option($v, $v, $selected);
                        }

                        $d .= Cp::input_select_footer();


                        $r .= '<tr>'.PHP_EOL;
                        $r .= Cp::tableCell('', Cp::quickDiv('defaultBold', $required.$row->field_name).Cp::quickDiv('default', $required.$row->field_description));
                        $r .= Cp::tableCell('', $d);
                        $r .= '</tr>'.PHP_EOL;
                    }
                }
            }
        }


        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::td('', '', '2');
        $r .= Cp::quickDiv('buttonWrapper', Cp::input_submit(__('kilvin::cp.update')));
        $r .= '</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        // END CUSTOM FIELDS

        $r .= '</table>'.PHP_EOL;

        $r.=  '</form>'.PHP_EOL;

        return $this->accountWrapper($title, $title, $r);
    }

   /**
    * Update Profile Data
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateProfile()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        // validate for unallowed blank values
        if (empty(Request::all())) {
            return Cp::unauthorizedAccess();
        }

        $core_fields = [
            'bday_y',
            'bday_m',
            'bday_d',
            'url',
            'location',
            'occupation',
            'interests',
            'bio'
        ];

        $fields_data = $members_data = [];

        foreach ($core_fields as $field) {
            $members_data[$field] = Request::input($field);

            if (in_array($field, ['bday_y', 'bday_m', 'bday_d']) && empty($members_data[$field])) {
                $members_data[$field] = null;
            }
        }

        $errors = [];

        $custom_fields = DB::table('member_fields')->get();

        foreach ($custom_fields as $field) {
            $key = 'm_field_'.$field->field_handle;

            if ($field->is_field_required == 1 && !Request::filled($key)) {
                $errors[] = __('kilvin::publish.The following field is required').'&nbsp;'.$field->field_name;
            }

            if ($field->field_type == 'textarea' && !Request::filled($key)) {
                $fields_data[$key] = '';
            } else {
                $fields_data[$key] = Request::input($key);
            }
        }

        if (!empty($errors)) {
            return Cp::errorMessage($errors);
        }

        if (isset($members_data['url']) && $members_data['url'] == 'https://') {
            $members_data['url'] = '';
        }

        if (is_numeric($members_data['bday_d']) and is_numeric($members_data['bday_m']))
        {
            $year = ($members_data['bday_y'] != '') ? $members_data['bday_y'] : date('Y');
            $mdays = Carbon::createFromDate($year, $members_data['bday_m'], 1)->daysInMonth;

            if ($members_data['bday_d'] > $mdays) {
                $members_data['bday_d'] = $mdays;
            }
        }

        if (count($members_data) > 0) {
            DB::table('members')
                ->where('id', $id)
                ->update($members_data);
        }

        if (count($fields_data) > 0) {
            DB::table('member_data')
                ->where('member_id', $id)
                ->update($fields_data);
        }

        return redirect(kilvinCpUrl('account/edit-profile/id='.$id.'/U=1'));
    }

   /**
    * Notification Settings Form
    *
    * These are not really used yet, but we're keeping them around for future usage
    *
    * @return string
    */
    public function notificationSettings()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        $title = __('kilvin::account.notification_settings');

        $query = DB::table('members')
            ->where('id', $id)
            ->first();

        foreach ($query as $key => $val) {
            $$key = $val;
        }

        // Build the form output
        $r  = Cp::formOpen(['action' => 'account/update-notification-settings']).
              Cp::input_hidden('id', $id).
              Cp::input_hidden('current_email', $query->email);

        if (Cp::pathVar('U')) {
            $r .= Cp::quickDiv('success-message', __('kilvin::account.notifications_updated'));
        }

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::th('', '', '2');

        $r .= $title;

        $r .= '</th>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $checkboxes = ['accept_admin_email', 'accept_user_email', 'notify_by_default', 'smart_notifications'];

        foreach ($checkboxes as $val) {
            $r .= '<tr>'.PHP_EOL;
            $r .= Cp::td('', '100%', '2');
            $r .= Cp::input_checkbox($val, 'y', ($$val == 'y') ? 1 : '').'&nbsp;'.__('kilvin::account.'.$val);
            $r .= '</td>'.PHP_EOL;
            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::td('', '', '2');
        $r .= Cp::quickDiv('buttonWrapper', Cp::input_submit(__('kilvin::cp.update')));
        $r .= '</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL;
        $r .= '</form>'.PHP_EOL;

        return $this->accountWrapper($title, $title, $r);
    }

   /**
    * Update Notification Settings
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateNotificationSettings()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        $data = [
            'accept_admin_email'    => (Request::filled('accept_admin_email'))  ? 'y' : 'n',
            'accept_user_email'     => (Request::filled('accept_user_email'))   ? 'y' : 'n',
            'notify_by_default'     => (Request::filled('notify_by_default'))   ? 'y' : 'n',
            'smart_notifications'   => (Request::filled('smart_notifications')) ? 'y' : 'n'
        ];

        DB::table('members')->where('id', $id)->update($data);

        return redirect(kilvinCpUrl('account/notification-settings/id='.$id.'/U=1'));
    }

   /**
    * Change Email/Password Form
    *
    *
    * @return string
    */
    public function emailPasswordForm()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        $email  = '';
        $message   = '';

        // ------------------------------------
        //  Show "successful update" message
        // ------------------------------------

        if (Cp::pathVar('U')) {
            $message = Cp::quickDiv('success-message', __('kilvin::account.settings_updated'));
        }

        $title = __('kilvin::account.email_and_password');

        // ------------------------------------
        //  Fetch screen_name + email
        // ------------------------------------

        $query = DB::table('members')
            ->where('id', $id)
            ->select('email', 'screen_name')
            ->first();

        $email          = $query->email;
        $screen_name    = $query->screen_name;

        // ------------------------------------
        //  Build the output
        // ------------------------------------

        $r  = Cp::formOpen(['action' => 'account/update-email-password']).
              Cp::input_hidden('id', $id);

        if (Cp::pathVar('U'))
        {
            $r .= $message;
        }

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::th('', '', '2');

        $r .= $title;

        $r .= '</th>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::account.screen_name')), '28%');
        $r .= Cp::tableCell('', Cp::input_text('screen_name', $screen_name, '40', '50', 'input', '100%'), '72%');
        $r .= '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::account.email')), '28%');
        $r .= Cp::tableCell('', Cp::input_text('email', $email, '40', '50', 'input', '100%'), '72%');
        $r .= '</tr>'.PHP_EOL;


        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::td('', '100%', '2');

        $r .= Cp::div('littlePadding')
             .Cp::quickDiv('itemTitle', __('kilvin::account.password_change'))
             .Cp::quickDiv('littlePadding', Cp::quickDiv('alert', __('kilvin::account.password_change_exp')))
             .Cp::quickDiv('highlight', __('kilvin::account.password_change_requires_login'))
             .'</div>'.PHP_EOL;

        $r .= Cp::quickDiv('itemTitle', __('kilvin::account.new_password'))
             .Cp::input_pass('password', '', '35', '32', 'input', '300px');

        $r .= Cp::div('littlePadding').
              Cp::quickDiv('itemTitle', __('kilvin::account.new_password_confirm')).
              Cp::input_pass('password_confirm', '', '35', '32', 'input', '300px').
              '</div>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::td('', '100%', '2');

        $r .= Cp::div('paddedWrapper').
              Cp::quickDiv('itemTitle', __('kilvin::account.existing_password')).
              Cp::quickDiv('littlePadding', Cp::quickDiv('highlight', __('kilvin::account.existing_password_exp'))).
              Cp::input_pass('current_password', '', '35', '32', 'input', '310px');

        $r .= '</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::td('', '', '2');
        $r .= Cp::quickDiv('buttonWrapper', Cp::input_submit(__('kilvin::cp.update')));
        $r .= '</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL;

        $r .= '</div>'.PHP_EOL;

        $r.=  '</form>'.PHP_EOL;

        return $this->accountWrapper($title, $title, $r);
    }

   /**
    * Update Email and/or Password
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateEmailPassword()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        if (empty(Request::all())) {
            return Cp::unauthorizedAccess();
        }

        if (!Request::filled('email') or !Request::input('screen_name'))  {
            return redirect(kilvinCpUrl('account/email-password-form/id='.$id));
        }

        // ------------------------------------
        //  Fetch screen_name + email
        // ------------------------------------

        $query = DB::table('members')
            ->where('id', $id)
            ->select('email', 'screen_name')
            ->first();

        $current_email          = $query->email;
        $current_screen_name    = $query->screen_name;

        // ------------------------------------
        //  Validate submitted data
        // ------------------------------------

        $VAL = new ValidateAccount(
            [
                'member_id'             => $id,
                'request_type'          => 'update', // new or update
                'require_password'      => true,
                'email'                 => Request::input('email'),
                'current_email'         => $current_email,
                'screen_name'           => Request::input('screen_name'),
                'current_screen_name'   => $current_screen_name,
                'password'              => Request::input('password'),
                'password_confirm'      => Request::input('password_confirm'),
                'current_password'      => Request::input('current_password')
            ]
        );

        $VAL->validateScreenName();
        $VAL->validateEmail();

        if (Request::filled('password')) {
            $VAL->validatePassword();
        }

        // ------------------------------------
        //  Display errors
        // ------------------------------------

        if (count($VAL->errors()) > 0) {
            return Cp::errorMessage($VAL->errors());
        }

        // ------------------------------------
        //  Assign the query data
        // ------------------------------------

        $data['screen_name'] = Request::input('screen_name');
        $data['email'] = Request::input('email');

        // Was a password submitted?
        if (Request::filled('password')) {
            $data['password'] = Hash::make(Request::input('password'));
        }

        DB::table('members')
            ->where('id', $id)
            ->update($data);

        // Write log file
        Cp::log($VAL->log_msg);

        return redirect(kilvinCpUrl('account/email-password-form/id='.$id.'/U=1'));
    }

   /**
    * CP Homepage Settings
    *
    * @return string
    */
    public function homepageDisplay()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        $prefs = [];

        $widgets = [
            'recentEntries'  => __('kilvin::account.recent_entries'),
            'siteStatistics' => __('kilvin::account.site_statistics'),
            'notepad'         => __('kilvin::account.notepad'),
        ];

        if (Session::access('can_access_admin') === true) {
            $widgets['memberSearchForm'] = __('kilvin::account.member_search_form');
            $widgets['recentMembers']    = __('kilvin::account.recent_members');
        }

        $query = DB::table('homepage_widgets')
            ->where('member_id', $id)
            ->orderBy('column')
            ->orderBy('order')
            ->get()
            ->keyBy('name');

        // Sort by Name
        if ($query->count() == 0) {
            asort($widgets);
        }

        // Sort by column and order
        if ($query->count() > 0) {
            $temp = $widgets;
            $new  = [];

            foreach($query as $row) {
                $new[$row->name] = $widgets[$row->name];
                unset($temp[$row->name]);
            }

            $widgets = array_merge($new, $temp);
        }

        $r  = Cp::formOpen(['action' => 'account/update-homepage-preferences']);
        $r .= Cp::input_hidden('id', $id);

        if (Cp::pathVar('U'))
        {
            $r .= Cp::div('');
            $r .= Cp::quickDiv('success-message', __('kilvin::account.preferences_updated'));
            $r .= '</div>'.PHP_EOL;
        }

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::tableQuickHeader('', __('account.Widget Name')).
              Cp::tableQuickHeader('', __('kilvin::account.Column')).
              Cp::tableQuickHeader('', __('kilvin::account.Order')).
              '</tr>'.PHP_EOL;

        $i = 0;

        foreach ($widgets as $method => $name)
        {
            $r .= '<tr>'.PHP_EOL;
            $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', $name));

            $column = (isset($query[$method])) ?  $query[$method]->column : '';

            $ls = ($column == 'l') ? 'selected="selected"' : '';
            $rs = ($column == 'r') ? 'selected="selected"' : '';

            $r .= Cp::tableCell(
                '',
                '<select name="column['.$method.']" >'.
                    '<option value="">'.__('kilvin::account.do_not_show').'</option>'.
                    '<option value="l" '.$ls.'>'.__('kilvin::account.left_column').'</option>'.
                    '<option value="r" '.$rs.'>'.__('kilvin::account.right_column').'</option>'.
                '</select>'
            );

            $order = (isset($query[$method])) ?  $query[$method]->order : '';

            $r .= Cp::tableCell(
                '',
                '<input type="text" size="2" maxlength="2" name="order['.$method.']" value="'.$order.'">'
            );

            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::td('', '', '4');
        $r .= Cp::quickDiv('buttonWrapper', Cp::input_submit(__('kilvin::cp.update')));
        $r .= '</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL;
        $r .= '</form>'.PHP_EOL;

        $title = __('kilvin::account.customize_homepage');

        return $this->accountWrapper($title, $title, $r);
    }

   /**
    * Update Homepage Settings
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateHomepagePreferences()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        $loc = Request::input('loc');

        $widgets = [
            'recentEntries'  => __('kilvin::account.recent_entries'),
            'siteStatistics' => __('kilvin::account.site_statistics'),
            'notepad'        => __('kilvin::account.notepad'),
        ];

        if (Session::access('can_access_admin') === true) {
            $widgets['memberSearchForm'] = __('kilvin::account.member_search_form');
            $widgets['recentMembers']    = __('kilvin::account.recent_members');
        }

        $column = Request::input('column');
        $order  = Request::input('order');

        if (!is_array($column)) {
            return redirect(kilvinCpUrl('account/homepage-display'));
        }

        foreach (array_keys($widgets) as $name) {
            if (isset($column[$name]) && in_array($column[$name], ['l', 'r'])) {
                $data[] = [
                    'member_id' => $id,
                    'name'      => $name,
                    'column'    => $column[$name],
                    'order'     => (isset($order[$name])) ? $order[$name] : 1
                ];
            }
        }

        DB::table('homepage_widgets')
            ->where('member_id', $id)
            ->delete();

        if (sizeof($data) > 0) {
            DB::table('homepage_widgets')->insert($data);
        }

        // Request they now order their display
        return redirect(kilvinCpUrl('account/homepage-display/id='.$id.'/U=2'));
    }

   /**
    * Choose your CP Theme
    *
    * @return string
    */
    public function chooseCpTheme()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        $title = __('kilvin::account.cp_theme');

        $r  = Cp::formOpen(['action' => 'account/save-cp-theme']);
        $r .= Cp::input_hidden('id', $id);

        $AD = new Administration;

        if (Cp::pathVar('U'))
        {
            $r .= Cp::quickDiv('success-message', __('kilvin::account.preferences_updated'));
        }

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::th('', '', '2');

        $r .= $title;

        $r .= '</th>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $theme = (Session::userdata('cp_theme') == '') ? Site::config('cp_theme') : Session::userdata('cp_theme');

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::account.choose_theme')), '50%');
        $r .= Cp::tableCell('', $AD->buildCpThemesPulldown($theme), '50%');
        $r .= '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::td('', '', '2');
        $r .= Cp::quickDiv('buttonWrapper', Cp::input_submit(__('kilvin::cp.update')));
        $r .= '</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL;
        $r .= '</form>'.PHP_EOL;

        return $this->accountWrapper($title, $title, $r);
    }

   /**
    * Save your CP Theme choice
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function saveCpTheme()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        if (!Request::input('cp_theme')) {
            return Cp::unauthorizedAccess();
        }

        DB::table('members')
            ->where('id', $id)
            ->update(['cp_theme' => Request::input('cp_theme')]);

        return redirect(kilvinCpUrl('account/choose-cp-theme/U=success/id='.$id));
    }

   /**
    * Edit Member Photo Form
    *
    * @return string
    */
    public function editPhoto()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Are photos enabled?
        // ------------------------------------

        if (Site::config('enable_photos') != 'y')
        {
            return Cp::errorMessage(__('kilvin::account.photos_not_enabled'));
        }

        $query = DB::table('members')
            ->where('id', $id)
            ->select('photo_filename', 'photo_width', 'photo_height')
            ->first();

        if ($query->photo_filename == '')
        {
            $cur_photo_url = '';
            $photo_width    = '';
            $photo_height   = '';
        }
        else
        {
            $cur_photo_url = Site::config('photo_url', TRUE).$query->photo_filename;
            $photo_width    = $query->photo_width;
            $photo_height   = $query->photo_height;
        }

        $title = __('kilvin::members.edit_photo');

        $r  = Cp::formOpen(['action' => 'account/upload-photo', 'enctype' => 'multipart/form-data']);
        $r .= Cp::input_hidden('id', $id);

        if (Cp::pathVar('U')) {
            $r .= Cp::quickDiv('success-message', __('kilvin::account.photo_updated'));
        }

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::th('', '', '2');

        $r .= $title;

        $r .= '</th>'.PHP_EOL.
              '</tr>'.PHP_EOL;


        if ($query->photo_filename != '')
        {
            $photo = '<img src="'.$cur_photo_url.'" border="0" width="'.$photo_width.'" height="'.$photo_height.'" title="'.__('kilvin::account.my_photo').'" />';
        }
        else
        {
            $photo = Cp::quickDiv('littlePadding', Cp::quickDiv('highlight', __('kilvin::members.no_photo_exists')));
        }

        $i = 0;

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::members.current_photo')), '35%');
        $r .= Cp::tableCell('', $photo, '65%');
        $r .= '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Set the default image meta values
        // ------------------------------------

        $max_kb = (Site::config('photo_max_kb') == '' OR Site::config('photo_max_kb') == 0) ? 50 : Site::config('photo_max_kb');
        $max_w  = (Site::config('photo_max_width') == '' OR Site::config('photo_max_width') == 0) ? 100 : Site::config('photo_max_width');
        $max_h  = (Site::config('photo_max_height') == '' OR Site::config('photo_max_height') == 0) ? 100 : Site::config('photo_max_height');
        $max_size = str_replace('%x', $max_w, __('kilvin::members.max_image_size'));
        $max_size = str_replace('%y', $max_h, $max_size);
        $max_size .= ' - '.$max_kb.'KB';



        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::members.upload_photo')), '35%');
        $r .= Cp::tableCell('', '<input type="file" name="userfile" size="20" class="input" />', '65%');
        $r .= '</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;


        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickDiv('littlePadding', Cp::quickSpan('highlight_alt', $max_size)), '35%');
        $r .= Cp::tableCell('', Cp::quickSpan('highlight_alt', __('kilvin::members.allowed_image_types')), '65%');
        $r .= '</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;


        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::td('', '', '2');
        $r .= Cp::quickDiv('buttonWrapper', Cp::input_submit(__('kilvin::members.upload_photo')).'&nbsp;'.Cp::input_submit(__('kilvin::members.remove_photo'), 'remove'));
        $r .= '</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL;
        $r .= '</form>'.PHP_EOL;

        return $this->accountWrapper($title, $title, $r);
    }

   /**
    * Update Member Photo
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function uploadPhoto()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        $edit_image     = 'edit-photo';
        $not_enabled    = 'photos_not_enabled';
        $remove         = 'remove_photo';
        $removed        = 'photo_removed';
        $updated        = 'photo_updated';

        // ------------------------------------
        //  Is this a remove request?
        // ------------------------------------

        if (!Request::filled('remove')) {
            if (Site::config('enable_photos') == 'n')  {
                return Cp::errorMessage(__('kilvin::account.'.$not_enabled));
            }
        }
        else
        {
            $query = DB::table('members')
                ->where('id', $id)
                ->select('photo_filename')
                ->first();

            if ($query->photo_filename == '') {
                return redirect(kilvinCpUrl('account/edit-photo/id='.$id));
            }

            DB::table('members')
                ->where('id', $id)
                ->update(
                [
                    'photo_filename' => '',
                    'photo_width' => '',
                    'photo_height' => ''
                ]);

            @unlink(Site::config('photo_path', TRUE).$query->photo_filename);

            return redirect(kilvinCpUrl('account/edit-photo/id='.$id));
        }

        // ------------------------------------
        //  Is there $_FILES data?
        // ------------------------------------

        if ( ! isset($_FILES['userfile'])) {
            return redirect(kilvinCpUrl('account/edit-photo/id='.$id));
        }

        // ------------------------------------
        //  Check the image size
        // ------------------------------------

        $size = ceil(($_FILES['userfile']['size']/1024));

        $max_size = (Site::config('photo_max_kb') == '' OR Site::config('photo_max_kb') == 0) ? 50 : Site::config('photo_max_kb');
        $max_size = preg_replace("/(\D+)/", "", $max_size);

        if ($size > $max_size)
        {
            return Cp::userError( str_replace('%s', $max_size, __('kilvin::account.image_max_size_exceeded')));
        }

        // ------------------------------------
        //  Is the upload path valid and writable?
        // ------------------------------------

        $upload_path = Site::config('photo_path', TRUE);

        if ( ! @is_dir($upload_path) OR ! is_writable($upload_path)) {
            return Cp::errorMessage(__('kilvin::account.image_upload_path_error'));
        }

        // ------------------------------------
        //  Set some defaults
        // ------------------------------------

        $filename = $_FILES['userfile']['name'];

        $max_width  = (Site::config('photo_max_width') == '' OR Site::config('photo_max_width') == 0) ? 200 : Site::config('photo_max_width');
        $max_height = (Site::config('photo_max_height') == '' OR Site::config('photo_max_height') == 0) ? 200 : Site::config('photo_max_height');
        $max_kb     = (Site::config('photo_max_kb') == '' OR Site::config('photo_max_kb') == 0) ? 300 : Site::config('photo_max_kb');

        // ------------------------------------
        //  Filename missing extension?
        // ------------------------------------

        if (strpos($filename, '.') === false) {
            return Cp::userError(__('kilvin::members.invalid_image_type'));
        }

        // ------------------------------------
        //  Is it an allowed image type?
        // ------------------------------------

        $x = explode('.', $filename);
        $extension = '.'.end($x);

        // We'll do a simple extension check now.
        // The file upload class will do a more thorough check later

        $types = array('.jpg', '.jpeg', '.gif', '.png');

        if ( ! in_array(strtolower($extension), $types)) {
            return Cp::userError( __('kilvin::members.invalid_image_type'));
        }

        // ------------------------------------
        //  Assign the name of the image
        // ------------------------------------

        $new_filename = 'photos_'.$id.strtolower($extension);

        // ------------------------------------
        //  Do they currently have a photo?
        // ------------------------------------

        $query = DB::table('members')
            ->where('id', $id)
            ->select('photo_filename')
            ->first();

        $old_filename = ($query->photo_filename == '') ? '' : $query->photo_filename;

        // ------------------------------------
        //  Upload the image
        // ------------------------------------


        // @todo - Use Laravel's approach or a library
        return Cp::errorMessage(__('Disabled for the time being, sorry.'));

        // ------------------------------------
        //  Do we need to resize?
        // ------------------------------------


        // ------------------------------------
        //  Update DB
        // ------------------------------------

        DB::table('members')
            ->where('id', $id)
            ->update(
            [
                'photo_filename' => $new_filename,
                'photo_width' => $width,
                'photo_height' => $height
            ]);

        // ------------------------------------
        //  Success message
        // ------------------------------------

        return redirect(kilvinCpUrl('account/'.$edit_image.'/id='.$id.'/U=1'));
    }

   /**
    * Edit Notepad form
    *
    * @return string
    */
    public function notepad()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        $title = __('kilvin::account.notepad');

        if (Session::userdata('member_group_id') != 1) {
            if ($id != Session::userdata('member_id')) {
                return $this->accountWrapper($title, $title, __('kilvin::account.only_self_notpad_access'));
            }
        }

        $query = DB::table('members')
            ->where('id', $id)
            ->select('notepad', 'notepad_size')
            ->first();

        $r  = Cp::formOpen(['action' => 'account/notepad-update']).
              Cp::input_hidden('id', $id);

        if (Cp::pathVar('U')) {
            $r .= Cp::quickDiv('success-message', __('kilvin::account.notepad_updated'));
        }

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::th('', '', '2');

        $r .= $title;

        $r .= '</th>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::td('', '100%', '2');
        $r .= __('kilvin::account.notepad_blurb');
        $r .= '</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::td('', '100%', '5');
        $r .= Cp::input_textarea('notepad', $query->notepad, $query->notepad_size, 'textarea', '100%');
        $r .= '</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::account.notepad_size')), '20%');
        $r .= Cp::tableCell('', Cp::input_text('notepad_size', $query->notepad_size, '4', '2', 'input', '40px'), '80%');
        $r .= '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::td('', '', '2');
        $r .= Cp::quickDiv('buttonWrapper', Cp::input_submit(__('kilvin::cp.update')));
        $r .= '</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL;

        $r .= '</form>'.PHP_EOL;

        return $this->accountWrapper($title, $title, $r);
    }

   /**
    * Update Notepad
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function notepadUpdate()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        if (Session::userdata('member_group_id') != 1) {
            if ($id != Session::userdata('member_id')) {
                return false;
            }
        }

        // validate for unallowed blank values
        if (empty(Request::only('notepad', 'notepad_size'))) {
            return Cp::unauthorizedAccess();
        }

        DB::table('members')
            ->where('id', $id)
            ->update(
            [
                'notepad' => Request::input('notepad'),
                'notepad_size' => (ceil(Request::input('notepad_size')) > 0) ? Request::input('notepad_size') : 10
            ]);

        return redirect(kilvinCpUrl('account/notepad/id='.$id.'/U=1'));
    }

   /**
    * Member Administration Form
    *
    * @return string
    */
    public function administration()
    {
        if ( ! Session::access('can_admin_members')) {
            return Cp::unauthorizedAccess();
        }

        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        $title = __('kilvin::account.administrative_options');

        $query = DB::table('members')
            ->where('id', $id)
            ->select('ip_address', 'in_authorlist', 'member_group_id', 'is_banned')
            ->first();

        foreach ($query as $key => $val) {
            $$key = $val;
        }

        $r  = Cp::formOpen(['action' => 'account/update-administration']).
              Cp::input_hidden('id', $id);

        if (Cp::pathVar('U')) {
            $r .= Cp::quickDiv('success-message', __('kilvin::account.administrative_options_updated'));
        }

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::th('', '', '2');

        $r .= $title;

        $r .= '</th>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        if (Session::access('can_admin_members')) {
            $query = DB::table('member_groups')
                ->select('member_groups.id AS member_group_id', 'group_name')
                ->orderBy('group_name');

            if (Session::userdata('member_group_id') != 1) {
                $query->where('is_locked', 'n');
            }

            $query = $query->get();

            if ($query->count() > 0) {
                $r .= '<tr>'.PHP_EOL;
                $r .= Cp::tableCell('', Cp::quickDiv('defaultBold', __('kilvin::account.member_group_assignment')).Cp::quickDiv('littlePadding', Cp::quickDiv('alert', __('kilvin::account.member_group_warning'))), '50%');

                $menu = Cp::input_select_header('member_group_id');

                foreach ($query as $row) {
                    // If the current user is not a Admin
                    // we'll limit the member groups in the list

                    if (Session::userdata('member_group_id') != 1) {
                        if ($row->member_group_id == 1) {
                            continue;
                        }
                    }

                    $menu .= Cp::input_select_option($row->member_group_id, $row->group_name, ($row->member_group_id == $member_group_id) ? 1 : '');
                }

                $menu .= Cp::input_select_footer();

                $r .= Cp::tableCell('', $menu, '80%');
                $r .= '</tr>'.PHP_EOL;

            }
        }

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::td('', '50%', 1);
        $r .= '<strong>'.__('kilvin::members.is_banned').'</strong>';
        $r .= '</td>'.PHP_EOL;

        $r .= Cp::tableCell('', __('kilvin::cp.yes').
                '&nbsp;'.
                Cp::input_radio('is_banned', 1, ($is_banned == 1) ? 1 : '').
                '&nbsp;'.__('kilvin::cp.no').
                '&nbsp;'.
                Cp::input_radio('is_banned', 0, ($is_banned == 0) ? 1 : ''),
            '60%'
        );

        $r .= '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::td('', '100%', '2');
        $r .= Cp::input_checkbox('in_authorlist', 'y', ($in_authorlist == 'y') ? 1 : '').'&nbsp;'.Cp::quickSpan('defaultBold', __('kilvin::account.include_in_multiauthor_list'));
        $r .= '</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::td('', '', '2');
        $r .= Cp::quickDiv('buttonWrapper', Cp::input_submit(__('kilvin::cp.update')));
        $r .= '</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL;
        $r .= '</form>'.PHP_EOL;

        return $this->accountWrapper($title, $title, $r);
    }

   /**
    * Update Member Admin options
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateAdministration()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        if ( ! Session::access('can_admin_members')) {
            return Cp::unauthorizedAccess();
        }

        if (empty(Request::all())) {
            return Cp::unauthorizedAccess();
        }

        if (Session::userdata('member_id') == $id && Request::input('is_banned') != 0) {
            return Cp::errorMessage(__('kilvin::members.cannot_ban_self'));
        }

        $data['in_authorlist'] = (Request::input('in_authorlist') == 'y') ? 'y' : 'n';
        $data['is_banned'] = (Request::input('is_banned') == 1) ? true : false;

        if (Request::input('member_group_id')) {
            if ( ! Session::access('can_admin_members')) {
                return Cp::unauthorizedAccess();
            }

            $data['member_group_id'] = Request::input('member_group_id');

            if ($data['member_group_id'] == 1) {
                if (Session::userdata('member_group_id') != 1) {
                    return Cp::unauthorizedAccess();
                }
            }

            if ($data['member_group_id'] != 1) {
                if (Session::userdata('member_id') == $id) {
                    return Cp::errorMessage(__('kilvin::account.admin_demotion_alert'));
                }
            }
        }

        DB::table('members')
            ->where('id', $id)
            ->update($data);

        return redirect(kilvinCpUrl('account/administration/id='.$id.'/U=1'));
    }

   /**
    * Edit User's Quick Links
    *
    * @return string
    */
    public function quicklinks()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        if (Session::userdata('member_group_id') != 1) {
            if ($id != Session::userdata('member_id')) {
                return $this->accountWrapper(
                    __('kilvin::account.quick_links'),
                    __('kilvin::account.quick_links'),
                    __('kilvin::account.only_self_qucklink_access'));
            }
        }

        $r = '';

        if (Cp::pathVar('U')) {
            $r .= Cp::quickDiv('success-message', __('kilvin::account.quicklinks_updated'));
        }

        $r .= Cp::quickDiv('tableHeading', __('kilvin::account.quick_links'));

        $r .= Cp::formOpen(['action' => 'account/quick-links-update']).
              Cp::input_hidden('id', $id);

        $r .= Cp::table('tableBorder', '0', '', '100%');

        $r .= '<tr>'.PHP_EOL
             .Cp::td('', '', 3)
             .__('kilvin::account.quick_link_description').'&nbsp;'.__('kilvin::account.quick_link_description_more')
             .'</td>'.PHP_EOL
             .'</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickDiv('defaultBold', __('kilvin::account.link_title'))).
              Cp::tableCell('', Cp::quickDiv('defaultBold', __('kilvin::account.link_url'))).
              Cp::tableCell('', Cp::quickDiv('defaultBold', __('kilvin::account.link_order'))).
              '</tr>'.PHP_EOL;

        $query = DB::table('members')
            ->where('id', $id)
            ->select('quick_links')
            ->first();

        $i = 0;

        if ($query->quick_links != '') {
            foreach (explode("\n", $query->quick_links) as $row) {
                $i++;
                $x = explode('|', $row);

                $title = (isset($x[0])) ? $x[0] : '';
                $link  = (isset($x[1])) ? $x[1] : '';
                $order = (isset($x[2])) ? $x[2] : $i;

                $r .= '<tr>'.PHP_EOL.
                      Cp::tableCell('', Cp::input_text('title[]', $title, '20', '40', 'input', '100%'), '40%').
                      Cp::tableCell('', Cp::input_text('link[]',  $link, '20', '120', 'input', '100%'), '55%').
                      Cp::tableCell('', Cp::input_text('order[]', $order, '2', '3', 'input', '30px'), '5%').
                      '</tr>'.PHP_EOL;
            }
        }

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::input_text('title[]',  '', '20', '40', 'input', '100%'), '40%').
              Cp::tableCell('', Cp::input_text('link[]',  'https://', '20', '120', 'input', '100%'), '60%').
              Cp::tableCell('', Cp::input_text('order[]', $i, '2', '3', 'input', '30px'), '5%').
              '</tr>'.PHP_EOL;



        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::td('', '', '3');
        $r .= Cp::quickDiv('bigPad', Cp::quickSpan('highlight', __('kilvin::account.quicklinks_delete_instructions')));
        $r .= '</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;


        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::td('', '', '3');
        $r .= Cp::quickDiv('buttonWrapper', Cp::input_submit(__('kilvin::cp.submit')));
        $r .= '</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL;
        $r .= '</form>'.PHP_EOL;

        return $this->accountWrapper(__('kilvin::account.quick_links'), __('kilvin::account.quick_links'), $r);
    }

   /**
    * Update Main Menu Tabs (via Quicklinks Update method)
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateTabs()
    {
        return $this->quickLinksUpdate(true);
    }

   /**
    * Update Quick Links or Main Menu Tabs
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function quickLinksUpdate($tabs = false)
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        if (Session::userdata('member_group_id') != 1 && $id != Session::userdata('member_id')) {
            return false;
        }

        if (empty(Request::all())) {
            return Cp::unauthorizedAccess();
        }

        $links = [];

        foreach(Request::input('title') as $i => $value) {

            $title = $value;
            $order = (is_numeric(Request::input('order')[$i])) ? Request::input('order')[$i] : 1;
            $link = (!empty(Request::input('link')[$i])) ? Request::input('link')[$i] : '';

            if (! empty($link) && !empty($title)) {
                $links[$order.'_'.$i] = $title.'|'.$link.'|'.$order;
            }
        }

        ksort($links, SORT_NUMERIC);

        $str = implode("\n", $links);

        if ($tabs == false)
        {
            DB::table('members')
                ->where('id', $id)
                ->update(['quick_links' => trim($str)]);

            $url = 'account/quicklinks/id='.$id.'/U=1';
        }
        else
        {
            DB::table('members')
                ->where('id', $id)
                ->update(['quick_tabs' => trim($str)]);

            $url = 'account/tab-manager/id='.$id.'/U=1';
        }

        return redirect(kilvinCpUrl($url));
    }

   /**
    * Edit Main Menu Tabs for User
    *
    * @return string
    */
    public function tabManager()
    {
        if (false === ($id = $this->fetchAuthId())) {
            return Cp::unauthorizedAccess();
        }

        if (Session::userdata('member_group_id') != 1) {
            if ($id != Session::userdata('member_id')) {
                return $this->accountWrapper(
                    __('kilvin::account.tab_manager'),
                    __('kilvin::account.tab_manager'),
                    __('kilvin::account.only_self_tab_manager_access'));
            }
        }

        // ------------------------------------
        //  Build the rows of previously saved links
        // ------------------------------------

        $query = DB::table('members')
            ->where('id', $id)
            ->select('quick_tabs')
            ->first();

        $i = 0;
        $total_tabs = 0;
        $hidden     = '';
        $current    = '';

        if ($query->quick_tabs == '') {
            $tabs_exist = false;
        } else {
            $tabs_exist = true;

            $xtabs = explode("\n", $query->quick_tabs);

            $total_tabs = count($xtabs);

            foreach ($xtabs as $row) {
                $x = explode('|', $row);

                $title = (isset($x[0])) ? $x[0] : '';
                $link  = (isset($x[1])) ? $x[1] : '';
                $order = (isset($x[2])) ? $x[2] : $i;

                $i++;

                if (!Cp::pathVar('link')) {
                    $current .= '<tr>'.PHP_EOL;

                    $current .= Cp::tableCell('', Cp::input_text('title[]', $title, '20', '40', 'input', '95%'), '70%');
                    $current .= Cp::tableCell('', Cp::input_text('order[]', $order, '2', '3', 'input', '30px'), '30%');

                    $current .= '</tr>'.PHP_EOL;
                } else {
                    $hidden .= Cp::input_hidden('title[]', $title);
                    $hidden .= Cp::input_hidden('order[]', $order);
                }

                if ($total_tabs <= 1 && Cp::pathVar('link')) {
                    $hidden .= Cp::input_hidden('order[]', $order);
                }

                $hidden .= Cp::input_hidden('link[]', $link);
            }
        }

        // ------------------------------------
        //  Type of request
        // ------------------------------------

        $new_link = (Cp::pathVar('link')) ? true : false;

        // ------------------------------------
        //  Create the output
        // ------------------------------------

        $r = '';

        if (Cp::pathVar('U')) {
            $r .= Cp::quickDiv('success-message', __('kilvin::account.tab_manager_updated'));
        }

        $r .= Cp::formOpen(['action' => 'account/update-tabs']).
              Cp::input_hidden('id', $id).
              $hidden;

        $r .= Cp::table('tableBorder', '0', '', '100%');

        $r .= '<tr>'.PHP_EOL.
            Cp::th('', '', 3).
            __('kilvin::account.tab_manager').
            '</th>'.PHP_EOL.
            '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL
             .Cp::td('', '', 3)
             .Cp::quickDiv('littlePadding', __('kilvin::account.tab_manager_description'))
             .'</td>'.PHP_EOL
             .'</tr>'.PHP_EOL;

        if ($new_link == false) {
            $r .=
                  '<tr>'.PHP_EOL
                 .Cp::td('', '', 3)
                 .Cp::quickDiv('littlePadding', Cp::quickDiv('highlight_alt', __('kilvin::account.tab_manager_instructions')))
                 .Cp::quickDiv('littlePadding', Cp::quickDiv('highlight_alt', __('kilvin::account.tab_manager_description_more')));
        }

        if ($tabs_exist == true && $new_link == false) {
            $r .= Cp::quickDiv('littlePadding', Cp::quickDiv('highlight', __('kilvin::account.quicklinks_delete_instructions')));
        }

        $r .= '</td>'.PHP_EOL.'</tr>'.PHP_EOL;

        if ($new_link == false) {
            if ($tabs_exist == true) {
                $r .= '<tr>'.PHP_EOL.
                      Cp::tableCell('', Cp::quickDiv('defaultBold', __('kilvin::account.tab_title'))).
                      Cp::tableCell('', Cp::quickDiv('defaultBold', __('kilvin::account.tab_order')));

                $r .= '</tr>'.PHP_EOL;

                $r .= $current;
            }
        } else {
            $r .= '</table>'.PHP_EOL;
            $r .= Cp::quickDiv('defaultSmall', '&nbsp;');

            $i++;

            $r .= Cp::input_hidden('order_'.$i, $i);

            $r .= Cp::table('tableBorder', '0', '', '100%');
            $r .=   '<tr>'.PHP_EOL.
                        Cp::th('', '', 2).
                            __('kilvin::account.tab_manager_create_new').
                        '</th>'.PHP_EOL.
                    '</tr>'.PHP_EOL;

            $r .= '<tr>'.PHP_EOL.
                  Cp::tableCell('', Cp::quickDiv('defaultBold', __('kilvin::account.new_tab_title'))).
                  Cp::tableCell(
                    '',
                    Cp::quickSpan(
                        'defaultBold',
                        __('kilvin::account.new_tab_url')
                    ).
                    '&nbsp;'.
                    Cp::quickSpan('default', __('kilvin::account.cannot_edit'))
                ).
              '</tr>'.PHP_EOL;

            $newlink = (Cp::pathVar('link')) ? base64_decode(Cp::pathVar('link')) : '';
            $linktitle = (Cp::pathVar('linkt')) ? base64_decode(Cp::pathVar('linkt')) : '';

            $r .= '<tr>'.PHP_EOL.
                  Cp::tableCell('', Cp::input_text('title[]', $linktitle, '20', '40', 'input', '100%'), '40%').
                  Cp::tableCell('', Cp::input_text('link[]',  $newlink, '20', '120', 'input', '100%', 'readonly'), '60%').
                  '</tr>'.PHP_EOL;
        }

        if ($new_link === true OR $tabs_exist === true) {
            $r .= '<tr>'.PHP_EOL;
            $r .= Cp::td('', '', '2');
            $r .= Cp::quickDiv(
                'buttonWrapper',
                Cp::input_submit(
                    ($new_link === false)
                        ? __('kilvin::cp.update')
                        : __('kilvin::account.tab_manager_newlink')
                )
            );
            $r .= '</td>'.PHP_EOL;
            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;
        $r .= '</form>'.PHP_EOL;

        return $this->accountWrapper(__('kilvin::account.tab_manager'), __('kilvin::account.tab_manager'), $r);
    }
}
