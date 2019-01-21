<?php

use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CmsSeeder extends Seeder
{
    public $data; // Data coming in from installer
    public $theme_path;
    public $system_path;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // ------------------------------------
        //  Encrypt password and Unique ID
        // ------------------------------------

        $unique_id = Uuid::uuid4();
        $password  = Hash::make($this->data['password']);

        $now = now()->toDateTimeString();

        $themes_path = $this->system_path.'resources'.DIRECTORY_SEPARATOR.'site_themes'.DIRECTORY_SEPARATOR;

        // -----------------------------------
        //  Default Site!
        // -----------------------------------

        DB::table('sites')
            ->insert([
                'id'          => 1,
                'site_name'   => $this->data['site_name'],
                'site_handle' => 'default-site'
            ]);

        // -----------------------------------
        //  Default Domain!
        // -----------------------------------

        DB::table('site_urls')
            ->insert([
                'id' => 1,
                'site_id'     => 1,
                'site_url'    => $this->data['site_url'],
                'public_path' => ''
            ]);

        // --------------------------------------------------------------------
        //  Default Weblog - Preferences, Fields, Statuses, Categories
        // --------------------------------------------------------------------

        DB::table('weblogs')
            ->insert([
                'id'                    => 1,
                'site_id'               => 1,
                'category_group_id'     => 1,
                'weblog_handle'         => 'default-site',
                'weblog_name'           => 'Default Site Weblog',
                'weblog_url'            => removeDoubleSlashes($this->data['site_url'].'/site/index/'),
                'total_entries'         => 1,
                'last_entry_date'       => $now,
                'status_group_id'       => 1,
                'default_status'        => 'open',
                'weblog_field_group_id' => 1,
            ]);

        // Custom Fields
        DB::table('weblog_field_groups')
            ->insert([
                'id' => 1,
                'site_id' => 1,
                'group_name' => 'Default Field Group'
            ]);

        $fields = [
            [
                1,
                'excerpt',
                'Excerpt',
                'Excerpts are optional hand-crafted summaries of your content.',
                '{"Textarea":{"rows":3}}',
            ],
            [
                2,
                'body',
                'Body',
                '',
                '{"Textarea":{"rows":3}}',
            ],
            [
                3,
                'extended',
                'Extended',
                '',
                '{"Textarea":{"rows":3}}',
            ]
        ];

        foreach($fields as $key => $field) {

            DB::table('weblog_fields')
                ->insert([
                    'id'                  => $field[0],
                    'site_id'             => 1,
                    'weblog_field_group_id' => 1,
                    'field_handle'        => $field[1],
                    'field_name'          => $field[2],
                    'field_instructions'  => $field[3],
                    'settings'            => $field[4],
                    'field_type'          => 'Textarea',
                ]);
        }

        // Custom statuses
        DB::table('status_groups')
            ->insert([
                'id' => 1,
                'site_id'         => 1,
                'group_name'      => 'Default Status Group'
            ]);

        DB::table('statuses')
            ->insert([
                'status_group_id'     => 1,
                'status'       => 'open',
                'status_order' => 1
            ]);

        DB::table('statuses')
            ->insert([
                'status_group_id' => 1,
                'status'       => 'closed',
                'status_order' => 2
            ]);


        // --------------------------------------------------------------------
        //  Default Weblog - Layout
        // --------------------------------------------------------------------

        DB::table('weblog_layout_tabs')
            ->insert([
                'id' => 1,
                'weblog_id' => 1,
                'tab_name' => 'Publish',
                'tab_order' => 1
            ]);

        DB::table('weblog_layout_fields')
            ->insert([
                'weblog_layout_tab_id' => 1,
                'field_handle' => 'excerpt',
                'field_order' => 1
            ]);

        DB::table('weblog_layout_fields')
            ->insert([
                'weblog_layout_tab_id' => 1,
                'field_handle' => 'body',
                'field_order' => 2
            ]);

        DB::table('weblog_layout_fields')
            ->insert([
                'weblog_layout_tab_id' => 1,
                'field_handle' => 'extended',
                'field_order' => 3
            ]);


        // --------------------------------------------------------------------
        //  Member Groups
        // --------------------------------------------------------------------

        // Member groups - Admins
        DB::table('member_groups')
            ->insert(
            [
                'id'   => 1,
                'site_id'           => 1,
                'group_name'        => 'Admins',
                'group_description' => ''
            ]);

        // Admin has no group preferences for they are AS GODS
        $prefs = [ ];

        foreach($prefs as $handle => $value) {
            DB::table('member_group_preferences')
                ->insert([
                    'member_group_id' => 1,
                    'handle'          => $handle,
                    'value'           => $value
                ]);
        }

        // Member Group - Members
        DB::table('member_groups')
            ->insert(
            [
                'id'   => 2,
                'site_id'           => 1,
                'group_name'        => 'Members',
                'group_description' => ''
            ]);

        $prefs = [
            'is_locked'                  => 'y',
            'can_view_offline_system'    => 'n',
            'can_access_cp'              => 'y',
            'can_access_content'         => 'n',
            'can_access_templates'       => 'n',
            'can_access_plugins'         => 'n',
            'can_access_admin'           => 'n',
            'can_admin_weblogs'          => 'n',
            'can_admin_members'          => 'n',
            'can_admin_utilities'        => 'n',
            'can_admin_preferences'      => 'n',
            'can_admin_plugins'          => 'n',
            'can_admin_templates'        => 'n',
            'can_edit_categories'        => 'n',
            'can_admin_asset_containers' => 'n',
            'can_view_other_entries'     => 'n',
            'can_edit_other_entries'     => 'n',
            'can_assign_post_authors'    => 'n',
            'can_delete_self_entries'    => 'n',
            'can_delete_all_entries'     => 'n',
            'can_delete_self'            => 'n',
            'mbr_delete_notify_emails'   => '',
            'include_in_authorlist'      => 'n',
            'can_access_cp_site_id_1'        => 'n',
            'can_access_offline_site_id_1'   => 'n',
        ];

        foreach($prefs as $handle => $value) {
            DB::table('member_group_preferences')
                ->insert([
                    'member_group_id' => 2,
                    'handle'          => $handle,
                    'value'           => $value
                ]);
        }

        // --------------------------------------------------------------------
        //  Default SuperAdmin User!
        // --------------------------------------------------------------------

        DB::table('members')
            ->insert(
            [
                'id'                => 1,
                'member_group_id'   => 1,
                'password'          => $password,
                'unique_id'         => $unique_id,
                'email'             => $this->data['email'],
                'screen_name'       => $this->data['screen_name'],
                'join_date'         => $now,
                'ip_address'        => $this->data['ip'],
                'total_entries'     => 1,
                'last_entry_date'   => $now,
                'quick_links'       => '',
                'remember_token'    => Str::random(60), // For Demo Server
                'language'          => $this->data['default_language']
            ]);

        DB::table('homepage_widgets')
            ->insert(
                [
                    [
                        'member_id' => 1,
                        'name' => 'recentEntries',
                        'column' => 'l',
                        'order' => 1
                    ],
                    [
                        'member_id' => 1,
                        'name' => 'siteStatistics',
                        'column' => 'l',
                        'order' => 2
                    ],
                    [
                        'member_id' => 1,
                        'name' => 'memberSearchForm',
                        'column' => 'r',
                        'order' => 1
                    ],
                    [
                        'member_id' => 1,
                        'name' => 'notepad',
                        'column' => 'r',
                        'order' => 2
                    ]
                ]
            );

        DB::table('member_data')->insert(['member_id' => 1]);

        // --------------------------------------------------------------------
        //  System Stats
        // --------------------------------------------------------------------

        DB::table('stats')
            ->insert(
                [
                    'total_members' => 1,
                    'total_entries' => 1,
                    'last_entry_date' => $now,
                    'recent_member' => $this->data['screen_name'],
                    'recent_member_id' => 1,
                    'last_cache_clear' => $now
                ]
            );

        // --------------------------------------------------------------------
        //  Default Categories
        // --------------------------------------------------------------------

        DB::table('category_groups')
            ->insert(
                [
                    'id' => 1,
                    'site_id' => 1,
                    'group_name' => 'Default Category Group'
                ]
            );

        $categories = [
            'Music', 'Travel', 'Photography', 'Learning', 'Outdoors'
        ];

        foreach($categories as $key => $category) {
            DB::table('categories')
                ->insert(
                    [
                        'id'                    => $key + 1,
                        'site_id'               => 1,
                        'category_group_id'     => 1,
                        'parent_id'             => 0,
                        'category_name'         => $category,
                        'category_url_title'    => $category,
                        'category_description'  => '',
                        'category_order'        => $key + 1
                    ]
                );
        }

        DB::table('weblog_entry_categories')
            ->insert(
                [
                    'weblog_entry_id'      => 1,
                    'category_id'   => 4
                ]
            );

        // --------------------------------------------------------------------
        //  First Weblog Entry! Yay!!
        // --------------------------------------------------------------------

        $body = <<<ENTRY
Thank you for choosing Kilvin CMS!

This entry contains helpful resources to help you get the most from Kilvin CMS and the Kilvin Community.

### Community Technical Support

Community technical support is handled through our Slack Channel. Our community is full of knowledgeable and helpful people that will often reply quickly to your technical questions. Please review the [Support](https://kilvincms.com/support) section of our site before posting in Slack.

### Premium Support

With our [support subscriptions](https://kilvincms.com/premium-support) you can receive premium support for Kilvin CMS from the maintainers of the code.

Get help on how to best begin your development process, how to organise your team of developers working on the same project for maximum productivity, and answers to prompt, in-depth answers to your technical questions from the experts.

Please review our [Premium Support](https://kilvincms.com/premium-support) page for additional information.

### Resources

- [Kilvin CMS - Documentation](https://kilvincms.com/docs/)

Love Kilvin CMS? Please tell your friends and professionals associates.

Enjoy!

**The Kilvin CMS Team**
ENTRY;

        DB::table('weblog_entries')
            ->insert(
            [
                'id'            => 1,
                'site_id'       => 1,
                'weblog_id'     => 1,
                'author_id'     => 1,
                'entry_date'    => $now,
                'updated_at'    => $now,
                'url_title'     => 'getting-started',
                'status'        => 'open'
            ]);

        DB::table('weblog_entry_data')
            ->insert(
            [
                'weblog_entry_id' => 1,
                'weblog_id'       => 1,
                'locale'          => 'en_US',
                'title'           => 'Getting Started with Kilvin CMS',
                'field_excerpt'   => '',
                'field_body'      => $body,
                'field_extended'  => ''
            ]);

        // --------------------------------------------------------------------
        //  Upload Prefs
        // --------------------------------------------------------------------

        DB::table('asset_containers')
            ->insert(
            [
                'id'            => 1,
                'site_id'       => 1,
                'name'          => 'Main Assets',
                'handle'        => 'main-assets',
                'driver'        => 'local',
                'allowed_types' => 'all',
                'allowed_mimes' => '',
                'configuration' => json_encode([
                    'root' => $this->data['uploads_path'],
                    'url'  => $this->data['uploads_url'],
                ]),
            ]);

        // --------------------------------------------------------------------
        //  Weblogs plugin
        // --------------------------------------------------------------------

        DB::table('plugins')
            ->insert(
                [
                    'plugin_name' => 'Weblogs',
                    'plugin_version' => '1.0.0',
                    'has_cp' => 'n'
                ]
            );

        // --------------------------------------------------------------------
        //  Parsedown plugin
        // --------------------------------------------------------------------

        DB::table('plugins')
            ->insert(
                [
                    'plugin_name' => 'Parsedown',
                    'plugin_version' => '1.0.0',
                    'has_cp' => 'n'
                ]
            );

        // --------------------------------------------------------------------
        //  Template Variable - Privacy Policy
        // --------------------------------------------------------------------

        DB::table('template_variables')
            ->insert(
                [
                    'site_id' => 1,
                    'variable_name' => 'privacy_policy',
                    'variable_data' => $this->privacyPolicy($this->data['site_url'], $this->data['email'])
                ]
            );
    }

    private function privacyPolicy($site_url, $admin_email)
    {
        return <<<EOT
# Privacy Notice
This privacy notice discloses the privacy practices for ({$site_url}). This privacy notice applies solely to information collected by this website. It will notify you of the following:

1. What personally identifiable information is collected from you through the website, how it is used and with whom it may be shared.
2. What choices are available to you regarding the use of your data.
3. The security procedures in place to protect the misuse of your information.
4. How you can correct any inaccuracies in the information.

## Information Collection, Use, and Sharing
We are the sole owners of the information collected on this site. We only have access to/collect information that you voluntarily give us via email or other direct contact from you. We will not sell or rent this information to anyone.

We will use your information to respond to you, regarding the reason you contacted us. We will not share your information with any third party outside of our organization, other than as necessary to fulfill your request, e.g. to ship an order.

Unless you ask us not to, we may contact you via email in the future to tell you about specials, new products or services, or changes to this privacy policy.

## Your Access to and Control Over Information
You may opt out of any future contacts from us at any time. You can do the following at any time by contacting us via the email address or phone number given on our website:

- See what data we have about you, if any.
- Change/correct any data we have about you.
- Have us delete any data we have about you.
- Express any concern you have about our use of your data.

## Security
We take precautions to protect your information. When you submit sensitive information via the website, your information is protected both online and offline.

Wherever we collect sensitive information (such as credit card data), that information is encrypted and transmitted to us in a secure way. You can verify this by looking for a lock icon in the address bar and looking for "https" at the beginning of the address of the Web page.

While we use encryption to protect sensitive information transmitted online, we also protect your information offline. Only employees who need the information to perform a specific job (for example, billing or customer service) are granted access to personally identifiable information. The computers/servers in which we store personally identifiable information are kept in a secure environment.

**If you feel that we are not abiding by this privacy policy, you should contact us immediately via {$admin_email}.**

EOT;
    }
}
