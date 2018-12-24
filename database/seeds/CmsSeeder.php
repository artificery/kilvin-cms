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

        // -----------------------------------
        //  Site - Template Group Templates
        // -----------------------------------

        // require $this->theme_path;

        // foreach ($template_matrix as $template)
        // {
        //  $name = $template[0];

        //  DB::table('templates')
        //      ->insert(
        //          [
        //              'folder' => '/',
        //              'template_name'  => $name,
        //              'template_type'  => $template[1],
        //              'template_data'  => $name(),
        //              'updated_at'     => $now,
        //              'last_author_id' => 1
        //          ]);
        // }

        // unset($template_matrix);

        // // -----------------------------------
        // //  RSS/ATOM Templates
        // // -----------------------------------

        // require $themes_path.'rss/rss.php';

        // DB::table('templates')
        //  ->insert(
        //      [
        //          'folder' => '/',
        //          'template_name'  => 'atom',
        //          'template_type'  => 'atom',
        //          'template_data'  => atom(),
        //          'updated_at'     => $now,
        //          'last_author_id' => 1
        //      ]);

        // DB::table('templates')
        //  ->insert(
        //      [
        //          'folder' => '/',
        //          'template_name'  => 'rss',
        //          'template_type'  => 'rss',
        //          'template_data'  => rss_2(),
        //          'updated_at'     => $now,
        //          'last_author_id' => 1
        //      ]);

        // unset($template_matrix);

        // // -----------------------------------
        // //  Search Templates
        // // -----------------------------------

        // require $themes_path.'search/search.php';

        // foreach ($template_matrix as $template)
        // {
        //  $name = $template[0];

        //  DB::table('templates')
        //      ->insert(
        //          [
        //              'folder'    => '/search',
        //              'template_name'  => ($name == 'search_index') ? 'index' : $name,
        //              'template_type'  => $template[1],
        //              'template_data'  => $name(),
        //              'updated_at'     => $now,
        //              'last_author_id' => 1
        //          ]);
        // }

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
                'weblog_url'            => remove_double_slashes($this->data['site_url'].'/site/index/'),
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
                json_encode(['textarea_num_rows' => 3])
            ],
            [
                2,
                'body',
                'Body',
                '',
                json_encode(['textarea_num_rows' => 10])
            ],
            [
                3,
                'extended',
                'Extended',
                '',
                json_encode(['textarea_num_rows' => 20])
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

Community technical support is handled through our Slack Channel. Our community is full of knowledgeable and helpful people that will often reply quickly to your technical questions. Please review the [Support](https://arliden.com/docs/support.html) section of our User Guide before posting in Slack.

### Premium Support

With our [support subscriptions](https://arliden.com/premium-support) you can receive premium support for Kilvin CMS from the maintainers of the code.

Get help on how to best begin your development process, how to organise your team of developers working on the same project for maximum productivity, and answers to prompt, in-depth answers to your technical questions from the experts.

Please review our [Premium Support](https://arliden.com/premium-support) page for additional information.

### Resources

- [Getting Started Guide](https://arliden.com/getting_started.html)
- [Quick Start Tutorial](https://arliden.com/quick_start.html)
- [Kilvin CMS - Documentation](https://arliden.com/docs/)
- [Kilvin CMS - FAQ](https://arliden.com/faq/)

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
        //  Groot plugin - @todo - This is an Example plugin and may be removed once we reach 1.0
        // --------------------------------------------------------------------

        DB::table('plugins')
            ->insert(
                [
                    'plugin_name' => 'Groot',
                    'plugin_version' => '1.0.0',
                    'has_cp' => 'n'
                ]
            );
    }
}
