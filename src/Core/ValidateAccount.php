<?php
namespace Kilvin\Core;

use Illuminate\Support\Facades\DB;
use Kilvin\Facades\Site;
use Illuminate\Support\Facades\Hash;
use Kilvin\Models\Member;

class ValidateAccount
{
    public $request_type        = 'update';
    public $require_password    = false;

    public $member_id;
    public $screen_name;
    public $current_screen_name;
    public $password;
    public $password_confirm;
    public $email;
    public $current_email;

    public $errors              = [];
    public $enable_log          = true;
    public $log_msg             = [];

    // ------------------------------------
    //  Constructor
    // ------------------------------------

    function __construct($data = [])
    {
        $vars = [
            'member_id',
            'screen_name',
            'current_screen_name',
            'password',
            'password_confirm',
            'current_password',
            'email',
            'current_email'
        ];

        if (is_array($data)) {
            foreach ($vars as $val) {
                $this->$val = (isset($data[$val])) ? $data[$val] : '';
            }
        }

        if (isset($data['request_type'])) {
            $this->request_type = (bool) $data['request_type'];
        }

        if (isset($data['require_password'])) {
            $this->require_password = (bool) $data['require_password'];
        }

        if ($this->require_password == true) {
            $this->checkForPassword();
        }
    }

    // ------------------------------------
    //  Password Safety Check
    // ------------------------------------

    private function checkForPassword()
    {
        if (empty($this->current_password)) {
            return $this->errors[] = __('kilvin::account.missing_current_password');
        }

        if (!$this->validateCredentials($this->member_id, $this->current_password)) {
            $this->errors[] = __('kilvin::account.invalid_password');
        }
    }

    // ------------------------------------
    //  Validate Screen Name
    // ------------------------------------

    public function validateScreenName()
    {
        $type = $this->request_type;

        if (empty($this->screen_name))
        {
            return $this->errors[] = __('kilvin::account.disallowed_screen_chars');
        }

        if (preg_match('/[\x7d<\x7b>]/', $this->screen_name))
        {
            return $this->errors[] = __('kilvin::account.disallowed_screen_chars');
        }

        if (!empty($this->current_screen_name))
        {
            if ($this->current_screen_name != $this->screen_name)
            {
                $type = 'new';

                if ($this->enable_log == true) {
                    $this->log_msg[] = __('kilvin::account.screen_name_changed').'&nbsp;'.$this->screen_name;
                }
            }
        }

        if ($type == 'new')
        {
            // ------------------------------------
            //  Is screen name banned?
            // ------------------------------------
            if (Session::banCheck('screen_name', $this->screen_name) OR trim(preg_replace("/&nbsp;*/", '', $this->screen_name)) == '')
            {
                return $this->errors[] = __('kilvin::account.screen_name_taken');
            }

            // ------------------------------------
            //  Is screen name taken?
            // ------------------------------------
            if (strtolower($this->current_screen_name) != strtolower($this->screen_name))
            {
            	$count = DB::table('members')
            		->where('screen_name', $this->screen_name)
            		->count();

                if ($count > 0) {
                    $this->errors[] = __('kilvin::account.screen_name_taken');
                }
            }
        }
    }

    // ------------------------------------
    //  Validate Password
    // ------------------------------------

    public function validatePassword()
    {
        // ------------------------------------
        //  Is password missing?
        // ------------------------------------

        if (empty($this->password) or empty($this->password_confirm)) {
           return $this->errors[] = __('kilvin::account.missing_password');
        }

        // ------------------------------------
        //  Is password min length correct?
        // ------------------------------------

        $len = Site::config('password_min_length');

        if (strlen($this->password) < $len) {
            return $this->errors[] = str_replace('%x', $len, __('kilvin::account.password_too_short'));
        }

        // ------------------------------------
        //  Is password max length correct?
        // ------------------------------------

        if (strlen($this->password) > 50) {
            return $this->errors[] = __('kilvin::account.password_too_long');
        }

        // ------------------------------------
        //  Is password the same as email?
        //  - Check for reversed password and related to email password
        // ------------------------------------

        $lc_user = strtolower($this->email);
        $lc_pass = strtolower($this->password);
        $nm_pass = strtr($lc_pass, 'elos', '3105');

        if ($lc_user == $lc_pass or $lc_user == strrev($lc_pass) or $lc_user == $nm_pass or $lc_user == strrev($nm_pass)) {
            return $this->errors[] = __('kilvin::account.password_based_on_email');
        }

        // ------------------------------------
        //  Do Password and confirm match?
        // ------------------------------------

        if ($this->password != $this->password_confirm) {
            return $this->errors[] = __('kilvin::account.missmatched_passwords');
        }
    }

    // ------------------------------------
    //  Validate Email
    // ------------------------------------

    public function validateEmail()
    {
        $type = $this->request_type;

        // ------------------------------------
        //  Is email missing?
        // ------------------------------------

        if (empty($this->email)) {
            return $this->errors[] = __('kilvin::account.missing_email');
        }

        // ------------------------------------
        //  Is email valid?
        // ------------------------------------

        if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->errors[] = __('kilvin::account.invalid_email_address');
        }

        // ------------------------------------
        //  Set validation type
        // ------------------------------------

        if (!empty($this->current_email))
        {
            if ($this->current_email != $this->email)
            {
                if ($this->enable_log == true) {
                    $this->log_msg = __('kilvin::account.email_changed').'&nbsp;'.$this->email;
                }

                $type = 'new';
            }
        }

        if ($type == 'new')
        {
            // ------------------------------------
            //  Is email banned?
            // ------------------------------------

            if (Session::banCheck('email', $this->email)) {
                return $this->errors[] = __('kilvin::account.email_taken');
            }

            // ------------------------------------
            //  Do we allow multiple identical emails?
            // ------------------------------------

            $member_id = (empty($this->member_id)) ? 0 : $this->member_id;

            $count = DB::table('members')
        		->where('email', $this->email)
                ->where('id', '!=', $member_id)
        		->count();

            if ($count > 0) {
                $this->errors[] = __('kilvin::account.email_taken');
            }
        }
    }


    // ------------------------------------
    //  Display errors
    // ------------------------------------

    public function errors()
    {
        return $this->errors;
    }

    // ------------------------------------------------------------------------

    /*
     *  Validates a plaintext password against the member's stored password
     *
     *  @param integer $member_id
     *  @param string $password
     *  @return boolean
     */
    public function validateCredentials($member_id, $password)
    {
        $member = Member::find($member_id);

        if (empty($member)) {
            return false;
        }

        return Hash::check($password, $member->getAuthPassword());
    }
}
