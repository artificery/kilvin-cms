<?php

namespace Kilvin\Cp;

use Kilvin\Facades\Cp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Kilvin\Facades\Site;
use Illuminate\Support\Facades\Request;
use Carbon\Carbon;
use Kilvin\Cp\Logging;
use Kilvin\Core\Localize;
use Kilvin\Core\Session;
use Symfony\Component\Finder\Finder;

class Administration
{
   /**
    * Request Handler
    *
    * @return mixed
    */
    public function run()
    {
		switch(Cp::segment(2))
		{
			case 'config-manager' :
				if ( ! Session::access('can_admin_preferences')) {
					return Cp::unauthorizedAccess();
				}

				return $this->configManager();

			case 'member-config-manager' :
				if ( ! Session::access('can_admin_preferences')) {
					return Cp::unauthorizedAccess();
				}

				return $this->memberConfigManager();

			case 'update-config-preferences' :

				if ( ! Session::access('can_admin_preferences')) {
					return Cp::unauthorizedAccess();
				}

				return $this->updateConfigPreferences();

			break;
			case 'utilities' :

				if ( ! Session::access('can_admin_utilities')) {
					return Cp::unauthorizedAccess();
				}

				$utilities = new Utilities;

				switch(Cp::segment(3)) {
					case 'view-logs'			 : return (new Logging)->viewLogs();
						break;
					case 'clear-cplogs'		 	 : return (new Logging)->clearCpLogs();
						break;
					case 'clear-cache-form'		 : return $utilities->clearCacheForm();
						break;
					case 'clear-caching'		 : return $utilities->clearCaching();
						break;
					case 'recount-statistics'	 : return $utilities->recountStatistics();
						break;
					case 'recount-preferences'	 : return $utilities->recountPreferences();
						break;
					case 'update-recount-preferences'	: return $utilities->updateRecountPreferences();
						break;
					case 'perform-recount'		 : return $utilities->performRecount();
						break;
					case 'perform-stats-recount' : return $utilities->performStatsRecount();
						break;
					case 'php-info'			 	 : return $utilities->php_info();
						break;
					}

				break;
		}

		return $this->homepage();
	}

   /**
    * Main Administration homepage
    *
    * @return  string
    */
	public function homepage()
	{
		Cp::$title = __('kilvin::admin.system-admin');
		Cp::$crumb = __('kilvin::admin.system-admin');

		$menu = [
			'site-preferences'	=>	[
				'general-preferences'			=> [
					'config-manager/general-preferences',
					'system offline site url password timezone localize localization time zone date format language domain path cookie cookies'
				],

				// These are in the .env file....which maybe we write on save?
				// 'email_preferences'				=> [
				// 	'config-manager/email_preferences',
				// 	'email SMTP sendmail PHP Mail batch webmaster tell-a-friend contact form'
				// ],


				'censoring-preferences'			=> [
					'config-manager/censoring-preferences',
					'censor censoring censored'
				],
			],


			'weblogs-administration'	=> [
				'weblog-management'		=>	[
					'weblogs-administration/weblogs-overview',
					'weblog weblogs posting'
				],
				'categories'			=>	[
					'weblogs-administration/category-overview',
					'category categories'
				],
				'field-management'	 	=>	[
					'weblogs-administration/fields-overview',
					'custom fields relational date textarea formatting'
				],
				'status-management'		=>	[
					'weblogs-administration/status-overview',
					'status statuses open close'
				],
				'weblog-preferences'			=>	[
					'config-manager/weblog-preferences',
					'category URL dynamic caching caches'
				]
			 ],

			'members-and-groups' 	=> [
				'register-member'		=> [
					'members/register-member',
					'register new member'
				],
				'view-members'			=> [
					'members/list-members',
					'view members memberlist email url join date'
				],
				'member-groups'		 	=> [
					'members/member-group-manager',
					'member groups super admin admins'
				],

				'member-profile-fields' => [
					'members/profile-fields',
					'custom member profile fields '
				],

				'member-preferences'			=> [
					'member-config-manager',
					'membership members member private message messages messaging photos photo registration activation'
				],

				'space_1'				=> '-',

				'member-search'		 	=> [
					'members/member-search',
					'search members'
				],

				'user-banning'			=> [
					'members/member-banning',
					'ban banning users banned'
				]
		 	],

		 	'asset-preferences'	=> [

		 		'asset-containers'		=>	[
					'weblogs-administration/asset-containers',
					'upload uploading paths images files directory assets'
				],

				'image-resizing'	 			=> [
					'config-manager/image-resizing',
					'image resize resizing thumbnail thumbnails GD netPBM imagemagick magick'
				],
		 	],


			'utilities'				=> [
				'view-log-files'		=>	[
					'utilities/view-logs',
					'view CP control panel logs '
				],

				'space_1'				=> '-',

				'clear-caching'		 	=>	[
					'utilities/clear-cache-form',
					'clear empty cache caches'
				],
				'recount-statistics'		 	=>	[
					'utilities/recount-statistics',
					'stats statistics recount redo'
				],
				'php-info'				=>	[
					'utilities/php-info',
					'php info information settings paths'
				],
		 	]
		];

		// ----------------------------------------
		//  Set Initial Display + JS
		// ----------------------------------------

		if (Request::input('keywords')) {
			$area = 'search_results';
		} else {
			$area = 'default_menu';

			if (Cp::segment(2) !== null and in_array(Cp::segment(2), array_keys($menu))) {
				$area = Cp::segment(2);
			}
		}

		Cp::$body_props .= ' onload="showHideMenu(\''.$area.'\');"';

        $js = <<<EOT
<script type="text/javascript">
function showHideMenu(contentId)
{
	$("#menu_contents").html($("#"+contentId).html());
}
</script>
EOT;
        Cp::$body  = $js;
		Cp::$body .= Cp::table('', '0', '', '100%');

		// Various sections of Admin area
		$left_menu = Cp::div('tableHeadingAlt').
			__('kilvin::admin.system-admin').
			'</div>'.PHP_EOL.
			Cp::div('profileMenuInner');

		// ----------------------------------------
		//  Build Left Menu AND default content, which is also the menu
		// ----------------------------------------

		$content = PHP_EOL.'<ul>'.PHP_EOL;

		foreach($menu as $key => $value) {
			$left_menu .= Cp::quickDiv(
				'navPad',
				Cp::anchor(
					kilvinCpUrl('administration/'.$key),
					__('kilvin::admin.'.$key)
				)
			);

			$content .= '<li>'.
						Cp::anchor(
							'administration/'.$key,
							__('kilvin::admin.'.$key)
						).
						'</li>'.PHP_EOL;
		}

		$content .= '</ul>'.PHP_EOL;

		$main_content = Cp::quickDiv('default', '', 'menu_contents').
			"<div id='default_menu' style='display:none;'>".
				Cp::heading(__('kilvin::admin.system-admin'), 2).
				__('kilvin::admin.system-admin-blurb').
				$content.
			'</div>'.PHP_EOL;

		// -------------------------------------
		//  Clean up Keywords
		// -------------------------------------

		$keywords = '';

		if (Request::filled('keywords')) {
			$keywords = Request::input('keywords');
			$search_terms = preg_split("/\s+/", strtolower(Request::input('keywords')));
			$search_results = '';
		}

		// -------------------------------------
		//  Build Content
		// -------------------------------------

		foreach ($menu as $key => $val) {
			$content = PHP_EOL.'<ul>'.PHP_EOL;

			foreach($val as $k => $v) {
				// A space between items. Adds clarity
				if (substr($k, 0, 6) == 'space_')
				{
					$content .= '</ul>'.PHP_EOL.PHP_EOL.'<ul>'.PHP_EOL;
					continue;
				}

				if ($key == 'members-and-groups' && $v[0] != 'member-config-manager') {
					$url = $v[0];
				} elseif (starts_with($v[0], 'weblogs-administration') && $v[0] != 'weblog-preferences') {
					$url = $v[0];
				} else {
					$url = 'administration/'.$v[0];
				}

				$content .= '<li>'.Cp::anchor($url, __('kilvin::admin.'.$k)).'</li>'.PHP_EOL;

				// Find areas that match keywords, a bit simplisitic but it works...
				if (!empty($search_terms)) {
					if (sizeof(array_intersect($search_terms, explode(' ', strtolower($v[1])))) > 0) {
						$search_results .=
							'<li>'.
								__('kilvin::admin.'.$key).
								' -> '.
								Cp::anchor($url, __('kilvin::admin.'.$k)).
							'</li>';
					}
				}
			}

			$content .= '</ul>'.PHP_EOL;

			$blurb = ('admin.'.$key.'-blurb' == __('kilvin::admin.'.$key.'-blurb')) ? '' : __('kilvin::admin.'.$key.'-blurb');

			$main_content .=  "<div id='".$key."' style='display:none;'>".
								Cp::heading(__('kilvin::admin.'.$key), 2).
								$blurb.
								$content.
							'</div>'.PHP_EOL;
		}

		// -------------------------------------
		//  Keywords Search
		// -------------------------------------

		if (!empty($search_terms)) {
			if (strlen($search_results) > 0) {
				$search_results = PHP_EOL.'<ul>'.PHP_EOL.$search_results.PHP_EOL.'</ul>';
			} else {
				$search_results = __('kilvin::admin.no_search_results');

				if (isset($search_terms[0]) && strtolower($search_terms[0]) === 'mufasa') {
					$search_results .= '<div style="font-size: 4em;">🦁</div>';
				}
			}

			$main_content .=  "<div id='search_results' style='display:none;'>".
								Cp::heading(__('kilvin::admin.search_results'), 2).
								$search_results.
							  '</div>';
		}

		// -------------------------------------
		//  Display Page
		// -------------------------------------

		$left_menu .= '</div>'.PHP_EOL.'<br>';

		// Add in the Search Form
		$left_menu .=  Cp::quickDiv('tableHeadingAlt', __('kilvin::admin.search'))
						.Cp::div('profileMenuInner')
						.	Cp::formOpen(['action' => 'administration'])
						.		Cp::input_text('keywords', $keywords, '20', '120', 'input', '98%')
						.		Cp::quickDiv('littlePadding', Cp::quickDiv('defaultRight', Cp::input_submit(__('kilvin::admin.search'))))
						.	'</form>'.PHP_EOL
						.'</div>'.PHP_EOL;

		// Create the Table
		$table_row = [
			'first' 	=> ['valign' => "top", 'width' => "220px", 'text' => $left_menu],
			'second'	=> ['class' => "default", 'width'  => "15px"],
			'third'		=> ['valign' => "top", 'text' => $main_content]
		];

		Cp::$body .= Cp::tableRow($table_row).
					  '</table>'.PHP_EOL;

	}

   /**
    * Configuration Data
    *
    * The list of all the config options and how to display them
    *
    * @return  array
    */
	private function configDataStructure()
	{
		return [

			'general-preferences' =>	[
				'is_system_on'				=> array('r', array('y' => 'yes', 'n' => 'no')),
				'is_site_on'				=> array('r', array('y' => 'yes', 'n' => 'no')),
				'site_debug'				=> ['s', ['0' => 'debug_zero', '1' => 'debug_one', '2' => 'debug_two']],
				'notification_sender_email'	=> '',
				'password_min_length'		=> '',
				'cookie_domain'				=> '',
				'cookie_path'				=> '',
				'site_timezone'			=> ['f', 'timezone'],
				'date_format'			=> ['s', ['Y-m-d' => Localize::format('Y-m-d', 'now')]],
				'time_format'			=> ['s', ['H:i'   => __('kilvin::admin.24_hour_time'), 'g:i A' => __('kilvin::admin.12_hour_time')]],
				'default_language'		=> ['f', 'language_menu'],
			],

			'weblog-preferences' =>	[
				'new_posts_clear_caches'	=> array('r', array('y' => 'yes', 'n' => 'no')),
				'word_separator'			=> array('s', array('dash' => 'dash', 'underscore' => 'underscore')),
			],

			'image-resizing' =>	[
				'enable_image_resizing' 	=> array('r', array('y' => 'yes', 'n' => 'no')),
				'image_resize_protocol'		=> ['s', ['gd2' => 'gd2', 'imagemagick' => 'imagemagick']],
				'image_library_path'		=> '',
				'thumbnail_prefix'			=> '',
			],

			'template-preferences' => [
				'save_tmpl_revisions' 		=> array('r', array('y' => 'yes', 'n' => 'no')),
				'max_tmpl_revisions'		=> '',
			],

			'censoring-preferences' => [
				'enable_censoring' 			=> array('r', array('y' => 'yes', 'n' => 'no')),
				'censor_replacement'		=> '',
				'censored_words'			=> array('t', array('rows' => '20', 'kill_pipes' => TRUE)),
			],
		];
	}

   /**
    * Subtext for Configruation options
    *
    * Secondary text for further explanations or details for an option
    *
    * @return  array
    */
	private function subtext()
	{
		return [
			'is_site_on'		    	=> array('is_site_on_explanation'),
			'is_system_on'		    	=> array('is_system_on_explanation'),
			'site_debug'				=> array('site_debug_explanation'),
			'default_member_group' 		=> array('group_assignment_defaults_to_two'),
			'notification_sender_email' => array('notification_sender_email_explanation'),
			'cookie_domain'				=> array('cookie_domain_explanation'),
			'cookie_path'				=> array('cookie_path_explain'),
			'censored_words'			=> array('censored_explanation', 'censored_wildcards'),
			'censor_replacement'		=> array('censor_replacement_info'),
			'enable_image_resizing'		=> array('enable_image_resizing_exp'),
			'image_resize_protocol'		=> array('image_resize_protocol_exp'),
			'image_library_path'		=> array('image_library_path_exp'),
			'thumbnail_prefix'			=> array('thumbnail_prefix_exp'),
			'save_tmpl_revisions'		=> array('template_rev_msg'),
			'max_tmpl_revisions'		=> array('max_revisions_exp'),
		];
	}

   /**
    * Abstracted Configuration page
    *
    * Based on the request it loads the relevant config data and displays the form
    *
    * @return  string
    */
	public function configManager()
	{
		if ( ! Session::access('can_admin_preferences')) {
			return Cp::unauthorizedAccess();
		}

		if (!($type = Cp::segment(3))) {
			return false;
		}

		// No funny business with the URL
		$allowed = [
			'general-preferences',
			'weblog-preferences',
			'member-preferences',
			'image-resizing',
			'template-preferences',
			'censoring-preferences',
		];

		if (!in_array($type, $allowed)) {
			abort(404);
		}

		$f_data = $this->configDataStructure();

		$subtext = $this->subtext();

		// ------------------------------------
		//  Build the output
		// ------------------------------------

		Cp::$body	 =	'';

		if (Cp::pathVar('U') or Cp::pathVar('msg') == 'updated') {
			Cp::$body .= Cp::quickDiv('success-message', __('kilvin::admin.preferences_updated'));
		}

		$return_loc = 'administration/config-manager/'.$type.'/U=1';

		if ($type === 'template-preferences') {
			$return_loc = 'templates_manager';
		}

		Cp::$body .= Cp::formOpen(
			[
				'action' => 'administration/update-config-preferences'
			],
			[
				'return_location' => $return_loc
			]
		);

		Cp::$body .=	Cp::table('tableBorder', '0', '', '100%');
		Cp::$body .=	'<tr>'.PHP_EOL;
		Cp::$body .=	Cp::td('tableHeading', '', '2');
		Cp::$body .=	__('kilvin::admin.'.$type);
		Cp::$body .=	'</td>'.PHP_EOL;
		Cp::$body .=	'</tr>'.PHP_EOL;

		$i = 0;

		// ------------------------------------
		//  Blast through the array
		// ------------------------------------

		foreach ($f_data[$type] as $key => $val) {
			Cp::$body	.=	'<tr>'.PHP_EOL;

			// If the form type is a textarea, we'll align the text at the top, otherwise, we'll center it

			if (is_array($val) AND $val[0] == 't')
			{
				Cp::$body .= Cp::td('', '50%', '', '', 'top');
			}
			else
			{
				Cp::$body .= Cp::td('', '50%', '');
			}

			// ------------------------------------
			//  Preference heading
			// ------------------------------------

			Cp::$body .= Cp::div('defaultBold');

			$label = ( ! is_array($val)) ? $key : '';

			Cp::$body .= __('kilvin::admin.'.$key);

			Cp::$body .= '</div>'.PHP_EOL;

			// ------------------------------------
			//  Preference sub-heading
			// ------------------------------------

			if (isset($subtext[$key]))
			{
				foreach ($subtext[$key] as $sub)
				{
					Cp::$body .= Cp::quickDiv('subtext', __('kilvin::admin.'.$sub));
				}
			}

			Cp::$body .= '</td>'.PHP_EOL;

			// ------------------------------------
			//  Preference value
			// ------------------------------------

			Cp::$body .= Cp::td('', '50%', '');

			if (is_array($val))
			{
				// ------------------------------------
				//  Drop-down menus
				// ------------------------------------

				if ($val[0] == 's')
				{
					Cp::$body .= Cp::input_select_header($key);

					foreach ($val[1] as $k => $v)
					{
						$selected = ($k == Site::config($key)) ? 1 : '';

						$value = ($key == 'date_format' or $key == 'time_format') ? $v : __('kilvin::admin.'.$v);

						Cp::$body .= Cp::input_select_option($k, $value, $selected);
					}

					Cp::$body .= Cp::input_select_footer();

				}
				elseif ($val[0] == 'r')
				{
					// ------------------------------------
					//  Radio buttons
					// ------------------------------------

					foreach ($val[1] as $k => $v)
					{

						if($k == 'y') {
							$selected = (Site::config($key) === true or Site::config($key) === 'y');
						} elseif($k == 'n') {
							$selected = (Site::config($key) === false or Site::config($key) === 'n');
						} else {
							$selected = ($k == Site::config($key)) ? 1 : '';
						}

						Cp::$body .= __('kilvin::admin.'.$v).'&nbsp;';
						Cp::$body .= Cp::input_radio($key, $k, $selected).'&nbsp;';
					}
				}
				elseif ($val[0] == 't')
				{
					// ------------------------------------
					//  Textarea fields
					// ------------------------------------

					// The "kill_pipes" index instructs us to
					// turn pipes into newlines

					if (isset($val[1]['kill_pipes']) AND $val[1]['kill_pipes'] === TRUE)
					{
						$text	= '';

						foreach (explode('|', Site::originalConfig($key)) as $exp)
						{
							$text .= $exp.PHP_EOL;
						}
					}
					else
					{
						$text = stripslashes(Site::originalConfig($key));
					}

					$rows = (isset($val[1]['rows'])) ? $val[1]['rows'] : '20';

					$text = str_replace("\\'", "'", $text);

					Cp::$body .= Cp::input_textarea($key, $text, $rows);

				}
				elseif ($val[0] == 'f')
				{
					// ------------------------------------
					//  Function calls
					// ------------------------------------

					switch ($val[1])
					{
						case 'language_menu'		: 	Cp::$body .= $this->availableLanguages(Site::config($key));
							break;
						case 'timezone'				: 	Cp::$body .= Localize::timezoneMenu(Site::config($key));
							break;
					}
				}
			}
			else
			{
				// ------------------------------------
				//  Text input fields
				// ------------------------------------

				$item = str_replace("\\'", "'", Site::originalConfig($key));

				Cp::$body .= Cp::input_text($key, $item, '20', '120', 'input', '100%');
			}

			Cp::$body .= '</td>'.PHP_EOL;
			Cp::$body .= '</tr>'.PHP_EOL;
		}

		Cp::$body .= '</table>'.PHP_EOL;

		Cp::$body .= Cp::quickDiv('littlePadding', Cp::input_submit(__('kilvin::cp.update')));

		Cp::$body .= '</form>'.PHP_EOL;

		Cp::$title  = __('kilvin::admin.'.$type);

		if (Cp::segment(2) == 'weblog_preferences') {
			Cp::$crumb  = Cp::anchor(
				'administration/weblogs-administration',
				 __('kilvin::admin.weblogs-administration')
			 );
			Cp::$crumb .= Cp::breadcrumbItem(__('kilvin::admin.'.$type));
		} elseif(Cp::segment(2) != 'template-preferences') {
			Cp::$crumb  = Cp::anchor('administration/site-preferences', __('kilvin::admin.site-preferences'));
			Cp::$crumb .= Cp::breadcrumbItem(__('kilvin::admin.'.$type));
		} else {
			Cp::$crumb .= __('kilvin::admin.'.$type);
		}
	}

   /**
    * Members/Accounts General Config Manager
    *
    * @return  string
    */
	public function memberConfigManager()
	{
		if ( ! Session::access('can_admin_preferences')) {
			return Cp::unauthorizedAccess();
		}

		$f_data = [
			'general-preferences'		=>
			[
				'default_member_group'	=> ['f', 'member_groups'],
				'enable_photos'			=> ['r', ['y' => 'yes', 'n' => 'no']],
				'photo_url'				=> '',
				'photo_path'			=> '',
				'photo_max_width'		=> '',
				'photo_max_height'		=> '',
				'photo_max_kb'			=> ''
			]
		];

		$subtext = [
			'default_member_group' 		=> ['group_assignment_defaults_to_two'],
			'photo_path'				=> ['must_be_path']
		];

		if (Cp::pathVar('U')) {
			Cp::$body .= Cp::quickDiv('success-message', __('kilvin::admin.preferences_updated'));
		}

		$r = Cp::formOpen(
			[
				'action' => 'administration/update-config-preferences'
			],
			[
				'return_location' => 'administration/member-config-manager/U=1'
			]
		);

		$r .= Cp::quickDiv('default', '', 'menu_contents');

		// ------------------------------------
		//  Blast through the array
		// ------------------------------------

		foreach ($f_data as $menu_head => $menu_array)
		{
			$r .= '<div id="'.$menu_head.'" style="display: block; padding:0; margin: 0;">';
			$r .= Cp::table('tableBorder', '0', '', '100%');
			$r .= '<tr>'.PHP_EOL;

			$r .= "<td class='tableHeadingAlt' id='".$menu_head."2' colspan='2'>";
			$r .= '&nbsp;'.__('kilvin::admin.'.$menu_head).'</td>'.PHP_EOL;
			$r .= '</tr>'.PHP_EOL;

			foreach ($menu_array as $key => $val)
			{
				$r	.=	'<tr>'.PHP_EOL;

				// If the form type is a textarea, we'll align the text at the top, otherwise, we'll center it
				if (is_array($val) AND $val[0] == 't') {
					$r .= Cp::td('', '50%', '', '', 'top');
				} else {
					$r .= Cp::td('', '50%', '');
				}

				// ------------------------------------
				//  Preference heading
				// ------------------------------------

				$r .= Cp::div('defaultBold');

				$label = ( ! is_array($val)) ? $key : '';

				$r .= __('kilvin::admin.'.$key);

				$r .= '</div>'.PHP_EOL;

				// ------------------------------------
				//  Preference sub-heading
				// ------------------------------------

				if (isset($subtext[$key])) {
					foreach ($subtext[$key] as $sub) {
						$r .= Cp::quickDiv('subtext', __('kilvin::admin.'.$sub));
					}
				}

				$r .= '</td>'.PHP_EOL;
				$r .= Cp::td('', '50%', '');

					if (is_array($val))
					{
						// ------------------------------------
						//  Drop-down menus
						// ------------------------------------

						if ($val[0] == 's')
						{
							$r .= Cp::input_select_header($key);

							foreach ($val[1] as $k => $v)
							{
								$selected = ($k == Site::originalConfig($key)) ? 1 : '';

								$r .= Cp::input_select_option($k, ( ! __('kilvin::admin.'.$v) ? $v : __('kilvin::admin.'.$v)), $selected);
							}

							$r .= Cp::input_select_footer();

						}
						elseif ($val[0] == 'r')
						{
							// ------------------------------------
							//  Radio buttons
							// ------------------------------------

							foreach ($val[1] as $k => $v)
							{
								$selected = ($k == Site::originalConfig($key)) ? 1 : '';

								$r .= __('kilvin::admin.'.$v).'&nbsp;';
								$r .= Cp::input_radio($key, $k, $selected).'&nbsp;';
							}
						}
						elseif ($val[0] == 'f')
						{
							// ------------------------------------
							//  Function calls
							// ------------------------------------

							switch ($val[1])
							{
								case 'member_groups' : $r .= $this->buildMemberGroupsPulldown();
									break;
							}
						}

					}
					else
					{
						// ------------------------------------
						//  Text input fields
						// ------------------------------------

						$item = str_replace("\\'", "'", Site::originalConfig($key));

						$r .= Cp::input_text($key, $item, '20', '120', 'input', '100%');
					}

				$r .= '</td>'.PHP_EOL;
			}

			$r .= '</tr>'.PHP_EOL;
			$r .= '</table>'.PHP_EOL;
			$r .= '</div>'.PHP_EOL;
		}

		$r .= Cp::quickDiv('littlePadding', Cp::input_submit(__('kilvin::cp.update')));

		$r .= '</form>'.PHP_EOL;

		// ------------------------------------
        //  Create Our All Encompassing Table of Member Goodness
        // ------------------------------------

        Cp::$body .= Cp::table('', '0', '', '100%');
		Cp::$body .= Cp::tableRow(['valign' => "top", 'text' => $r]).
					  '</table>'.PHP_EOL;

		Cp::$title = __('kilvin::admin.member-preferences');
		Cp::$crumb =
			Cp::anchor('administration/members-and-groups', __('kilvin::admin.members-and-groups')).
			Cp::breadcrumbItem(__('kilvin::admin.member-preferences'));
	}

   /**
    * Builds a Member Groups Pulldown
    *
    * @return  string
    */
	private function buildMemberGroupsPulldown()
	{
    	$query = DB::table('member_groups')
    		->select('member_groups.id AS member_group_id', 'group_name')
    		->where('id', '!=', 1)
    		->orderBy('group_name')
    		->get();

		$r = Cp::input_select_header('default_member_group');

		foreach ($query as $row)
		{
			$group_name = $row->group_name;

			$selected = ($row->member_group_id == Site::config('default_member_group')) ? 1 : '';

			$r .= Cp::input_select_option($row->member_group_id, $group_name, $selected);
		}

		$r .= Cp::input_select_footer();

		return $r;
	}

   /**
    * Update Config Options in DB
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
	function updateConfigPreferences()
	{
		if ( ! Session::access('can_admin_preferences')) {
			return Cp::unauthorizedAccess();
		}

		// @todo - Probably bogus, just set a default
		$loc = Request::input('return_location');

		// We'll format censored words if they happen to cross our path
		if (Request::filled('censored_words')) {
			$censored_words = Request::input('censored_words');
			$censored_words = str_replace(PHP_EOL, '|', $censored_words);
			$censored_words = preg_replace("#\s+#", "", $censored_words);
		}

		// ------------------------------------
		//  Do path checks if needed
		// ------------------------------------

		$paths = ['photo_path'];

		foreach ($paths as $val) {
			if (Request::filled($val)) {
				$fp = Request::input($val);

				$fp = str_replace('{PUBLIC_PATH}', Site::config('PUBLIC_PATH'), $fp);
				$fp = str_replace('{STORAGE_PATH}', Site::config('STORAGE_PATH'), $fp);

				if ( ! @is_dir($fp)) {
					$msg  = Cp::quickDiv('littlePadding', __('kilvin::admin.invalid_path'));
					$msg .= Cp::quickDiv('highlight', $fp);

					return Cp::errorMessage($msg);
				}

				if ( ! @is_writable($fp)) {
					$msg  = Cp::quickDiv('littlePadding', __('kilvin::admin.not_writable_path'));
					$msg .= Cp::quickDiv('highlight', $fp);

					return Cp::errorMessage($msg);
				}
			}
		}

		// ------------------------------------
		//  Preferences Stored in Database For Site
		// ------------------------------------

		$prefs = DB::table('site_preferences')
			->where('site_id', Site::config('site_id'))
			->value('value', 'handle');

		$update_prefs = [];

		foreach(Site::preferenceKeys() as $value) {
			if (Request::has($value)) {
				$update_prefs[$value] = Request::input($value);
			}
		}

		if (!empty($update_prefs)) {
			foreach($update_prefs as $handle => $value) {
				DB::table('site_preferences')
					->where('site_id', Site::config('site_id'))
					->where('handle', $handle)
					->update(
						[
							'value' => $value
						]);
			}
		}

		// ------------------------------------
		//  Certain Preferences go in .env file
		// ------------------------------------

		$this->updateEnvironmentFile();

		// ------------------------------------
		//  Redirect
		// ------------------------------------

		if ($loc === 'templates_manager') {
			return redirect(kilvinCpUrl('administration/config-manager/template-preferences/msg=updated'));
		}

		return redirect(kilvinCpUrl($loc));
	}

   /**
    * Update .env file
    *
    * There are two settings that go into .env instead of the DB
    *
    * @return bool
    */
	function updateEnvironmentFile()
	{
		// Convert the y/n to true/false

		$allowed = [
			'KILVIN_IS_SYSTEM_ON'      => 'is_system_on',
            'KILVIN_DISABLE_EVENTS'    => 'disable_events',
		];

		$data = [];

		foreach($allowed as $name => $value) {
			if (Request::filled($value)) {
				$data[$name] = (Request::input($value) == 'y') ? 'true' : 'false';
			}
		}

		if (empty($data)) {
			return;
		}

		$this->writeNewEnvironmentFileWith($data);
	}

   /**
     * Write a new environment file with the given key.
     *
     * @param  array  $new
     * @return void
     */
    protected function writeNewEnvironmentFileWith($new)
    {
        // The example is what starts us off
        $string = file_get_contents(app()->environmentFilePath());

        foreach($new as $key => $value) {
            $string = preg_replace(
                $this->keyReplacementPattern($key),
                $key.'='.$this->protectEnvValue($value),
                $string
            );
        }

        file_put_contents(app()->environmentFilePath(), $string);
    }

    // --------------------------------------------------------------------

    /**
     * Protect an .env value if it has special chars
     *
     * @return string
     */
    protected function protectEnvValue($value)
    {
        // @todo
        return $value;
    }

    // --------------------------------------------------------------------

    /**
     * Get a regex pattern that will match env APP_KEY with any random key.
     *
     * @return string
     */
    protected function keyReplacementPattern($key)
    {
        return "/^".$key."\=.*$/m";
    }

   /**
    * Build Available Languages Pulldown
    *
    * @return string
    */
    private function availableLanguages($default)
    {
        $source_dir = realpath( __DIR__.'/../../resources/language');

        foreach (Finder::create()->in($source_dir)->directories()->depth(0) as $dir) {
            $directories[] = $dir->getFilename();
        }

        $r  = "<div class='default'>";
        $r .= "<select name='default_language' class='select'>\n";

        foreach ($directories as $dir)
        {
            $selected = ($dir == $default) ? " selected='selected'" : '';
            $r .= "<option value='{$dir}'{$selected}>".$dir."</option>\n";
        }

        $r .= "</select>";
        $r .= "</div>";

        return $r;
    }
}

