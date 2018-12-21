<?php

namespace Kilvin\Core;

use Kilvin\Facades\Site;

class JsCalendar
{
    public function calendar()
    {
		// ------------------------------------
		//  Set-up our preferences
		// ------------------------------------

		$date_fmt = (Session::userdata('date_format') != '') ? Session::userdata('date_format') : Site::config('date_format');
		$time_fmt = (Session::userdata('time_format') != '') ? Session::userdata('time_format') : Site::config('time_format');

		$days = '';

		$daysList = [
			'Sun',
			'Mon',
			'Tue',
			'Wed',
			'Thu',
			'Fri',
			'Sat'
		];

		foreach ($daysList as $val) {
			$days .= "'".__('kilvin::core.'.$val)."',";
		}

		$days .= substr($days, 0, -1);

		$months = '';

		$monthList = [
			'January',
			'February',
			'March',
			'April',
			'May',
			'June',
			'July',
			'August',
			'September',
			'October',
			'November',
			'December'
		];

		foreach ($monthList as $val) {
			$months .= "'".__('kilvin::core.'.$val)."',";
		}

		$months .= substr($months, 0, -1);

		// ------------------------------------
		//  Write the JavaScript
		// ------------------------------------

		ob_start();
		?>
		<script type="text/javascript">

		var date_format		= '<?php echo $date_fmt; ?>';
		var time_format		= '<?php echo $time_fmt; ?>';
		var days		= new Array(<?php echo $days; ?>);
		var months		= new Array(<?php echo $months; ?>);
		var last_click	= new Array();
		var current_month  = '';
		var current_year   = '';
		var last_date  = '';

		function calendar(id, d, highlight)
		{
			this.id			= id;
			this.highlight	= highlight;
			this.date_obj	= d;
			this.write		= build_calendar;
			this.total_days	= total_days;
			this.month		= d.getMonth();
			this.date		= d.getDate();
			this.day		= d.getDay();
			this.year		= d.getFullYear();
			this.hours		= d.getHours();
			this.minutes	= d.getMinutes();
			this.seconds	= d.getSeconds();
			this.date_str	= date_str;

			if (highlight == false)
			{
				this.selected_date = '';
			}
			else
			{
				this.selected_date = this.year + '' + this.month + '' + this.date;
			}


			//	Set the "selected date"

			// As we toggle from month to month we need a way
			// to recall which date was originally highlighted
			// so when we return to that month the state will be
			// retained.  Well set a global variable containing
			// a string representing the year/month/day

			//get the first day of the month's day
			d.setDate(1);

			this.firstDay = d.getDay();

			//then reset the date object to the correct date
			d.setDate(this.date);
		}

		//	Build the body of the calendar

		function build_calendar()
		{
			var str = '';

			//	Calendar Heading
			str += '<div id="cal' + this.id + '">';
			str += '<table class="cms-calendar" cellspacing="0" cellpadding="0" border="0" align="center">';
			str += '<tr class="cms-calendar-heading">';
			str += '<td class="cms-calendar-navleft" onclick="change_month(-1, \'' + this.id + '\')">◀<\/td>';
			str += '<td colspan="5">' + months[this.month] + ' ' + this.year + '<\/td>';
			str += '<td class="cms-calendar-navright" onclick="change_month(1, \'' + this.id + '\')">▶<\/td>';
			str += '<\/tr>';

			//	Day Names

			str += '<tr>';

			for (i = 0; i < 7; i++)
			{
				str += '<td class="cms-calendar-dayheading">' + days[i] + '<\/td>';
			}

			str += '<\/tr>';

			//	Day Cells
			str += '<tr class="cms-calendar-first-row">';

			selDate = (last_date != '') ? last_date : this.date;

			for (j = 0; j < 42; j++)
			{
				var displayNum = (j - this.firstDay + 1);

				if (j < this.firstDay) // leading empty cells
				{
					str += '<td class="cms-calendar-blanktop">&nbsp;<\/td>';
				}
				else if (displayNum == selDate && this.highlight == true) // Selected date
				{
					str += '<td id="' + this.id +'selected" class="cms-calendar-daycells dayselected" onclick="set_date(this,\'' + this.id + '\')">' + displayNum + '<\/td>';
				}
				else if (displayNum > this.total_days())
				{
					str += '<td class="cms-calendar-blankbottom">&nbsp;<\/td>'; // trailing empty cells
				}
				else  // Unselected days
				{
					str += '<td id="" class="cms-calendar-daycells" onclick="set_date(this,\'' + this.id + '\'); return false;">' + displayNum + '<\/td>';
				}

				if (j % 7 == 6)
				{
					str += '<\/tr><tr>';
				}
			}

			str += '<\/tr>';
			str += '<\/table>';
			str += '<\/div>';

			return str;
		}

		//	Total number of days in a month

		function total_days()
		{
			switch(this.month)
			{
				case 1: // Check for leap year
					if ((  this.date_obj.getFullYear() % 4 == 0
						&& this.date_obj.getFullYear() % 100 != 0)
						|| this.date_obj.getFullYear() % 400 == 0)
						return 29;
					else
						return 28;
				case 3:
					return 30;
				case 5:
					return 30;
				case 8:
					return 30;
				case 10:
					return 30
				default:
					return 31;
			}
		}

		//	Clear Field

		function clear_field(id)
		{
			$('#'+id).val('');

			$('#'+id + "selected").attr('class', 'cms-calendar-daycells').attr('id', '');

			cal = eval(id); // id being the variable name
			cal.selected_date = '';
		}


		//	Set date to now
		function set_to_now(id)
		{
			$('#' + id + "selected").attr('class', 'cms-calendar-daycells').attr('id', '');

			$('#cal'+id).html('<div id="tempcal'+id+'">&nbsp;<'+'/div>');

			var nowDate = new Date();

			current_month	= nowDate.getMonth();
			current_year	= nowDate.getFullYear();
			current_date	= nowDate.getDate();

			oldcal = eval(id);
			oldcal.selected_date = current_year + '' + current_month + '' + current_date;

			oldcal.date_obj.setMonth(current_month);
			oldcal.date_obj.setYear(current_year);

			cal = new calendar(id, nowDate, true);
			cal.selected_date = current_year + '' + current_month + '' + current_date;

			last_date = cal.date;

			$('#tempcal'+id).html(cal.write());

			$('#'+id).val(cal.date_str('y'));
		}


		//	Set date to what is in the field
		var lastDates = new Array();

		function update_calendar(id, dateValue)
		{
			cal = eval(id);

			if (lastDates[id] == dateValue) {
				return;
			}

			lastDates[id] = dateValue;

			var fieldString = dateValue.replace(/\s+/g, ' ');

			while (fieldString.substring(0,1) == ' ')
			{
				fieldString = fieldString.substring(1, fieldString.length);
			}

			var dateString = fieldString.split(' ');
			var dateParts = dateString[0].split('-')

			if (dateParts.length < 3) return;
			var newYear  = dateParts[0];
			var newMonth = dateParts[1];
			var newDay   = dateParts[2];

			if (isNaN(newDay)  || newDay < 1 || (newDay.length != 1 && newDay.length != 2)) return;
			if (isNaN(newYear) || newYear < 1 || newYear.length != 4) return;
			if (isNaN(newMonth) || newMonth < 1 || (newMonth.length != 1 && newMonth.length != 2)) return;

			if (newMonth > 12) newMonth = 12;

			if (newDay > 28)
			{
				switch(newMonth - 1)
				{
					case 1: // Check for leap year
						if ((newYear % 4 == 0 && newYear % 100 != 0) || newYear % 400 == 0)
						{
							if (newDay > 29) newDay = 29;
						}
						else
						{
							if (newDay > 28) newDay = 28;
						}
					case 3:
						if (newDay > 30) newDay = 30;
					case 5:
						if (newDay > 30) newDay = 30;
					case 8:
						if (newDay > 30) newDay = 30;
					case 10:
						if (newDay > 30) newDay = 30;
					default:
						if (newDay > 31) newDay = 31;
				}
			}

			$('#' + id + "selected").attr('class', 'cms-calendar-daycells').attr('id', '');

			$('#cal'+id).html('<div id="tempcal'+id+'">&nbsp;</div>');

			var nowDate = new Date();
			nowDate.setDate(newDay);
			nowDate.setMonth(newMonth - 1);
			nowDate.setYear(newYear);
			nowDate.setHours(12);

			cal.date_obj.setMonth(newMonth - 1);
			cal.date_obj.setYear(newYear);

			current_month	= nowDate.getMonth();
			current_year	= nowDate.getFullYear();
			last_date		= newDay;

			cal = new calendar(id, nowDate, true);

			$('#tempcal'+id).html(cal.write());
		}


		//	Set the date

		function set_date(td, cal)
		{
			cal = eval(cal);

			// If the user is clicking a cell that is already
			// selected we'll de-select it and clear the form field

			if (last_click[cal.id] == td.firstChild.nodeValue)
			{
				td.className = "cms-calendar-daycells";
				last_click[cal.id] = '';
				remove_date(cal);
				cal.selected_date =  '';
				return;
			}

			// Onward!

			$('#' + cal.id + "selected").attr('class', 'cms-calendar-daycells').attr('id', '');

			td.className = "cms-calendar-daycells dayselected";
			td.id = cal.id + "selected";

			cal.selected_date = cal.date_obj.getFullYear() + '' + cal.date_obj.getMonth() + '' + cal.date;
			cal.date_obj.setDate(td.firstChild.nodeValue);
			cal = new calendar(cal.id, cal.date_obj, true);
			cal.selected_date = cal.date_obj.getFullYear() + '' + cal.date_obj.getMonth() + '' + cal.date;

			last_date = cal.date;

			//cal.date

			last_click[cal.id] = cal.date;

			// Insert the date into the form

			insert_date(cal);
		}


		//	Insert the date into the form field

		function insert_date(cal)
		{
			cal = eval(cal);

			field = $('#' + cal.id);

			if (field.val() == '')
			{
				field.val(cal.date_str('y'));
			}
			else
			{
				time = field.val().substring(10);

				field.val(cal.date_str('n') + time);
			}
		}

		//	Remove the date from the form field

		function remove_date(cal)
		{
			cal = eval(cal);

			fval = eval("document.getElementById('entryform')." + cal.id);
			fval.value = '';
		}

		//	Change to a new month

		function change_month(mo, cal)
		{
			cal = eval(cal);

			if (current_month != '')
			{
				cal.date_obj.setMonth(current_month);
				cal.date_obj.setYear(current_year);

				current_month	= '';
				current_year	= '';
			}

			var newMonth = cal.date_obj.getMonth() + mo;
			var newDate  = cal.date_obj.getDate();

			if (newMonth == 12)
			{
				cal.date_obj.setYear(cal.date_obj.getFullYear() + 1)
				newMonth = 0;
			}

			if (newMonth == -1)
			{
				cal.date_obj.setYear(cal.date_obj.getFullYear() - 1)
				newMonth = 11;
			}

			if (newDate > 28)
			{
				var newYear = cal.date_obj.getFullYear();

				switch(newMonth)
				{
					case 1: // Check for leap year
						if ((newYear % 4 == 0 && newYear % 100 != 0) || newYear % 400 == 0)
						{
							if (newDate > 29) newDate = 29;
						}
						else
						{
							if (newDate > 28) newDate = 28;
						}
					case 3:
						if (newDate > 30) newDate = 30;
					case 5:
						if (newDate > 30) newDate = 30;
					case 8:
						if (newDate > 30) newDate = 30;
					case 10:
						if (newDate > 30) newDate = 30;
					default:
						if (newDate > 31) newDate = 31;
				}
			}

			cal.date_obj.setDate(newDate);
			cal.date_obj.setMonth(newMonth);
			new_mdy	= cal.date_obj.getFullYear() + '' + cal.date_obj.getMonth() + '' + cal.date;

			highlight = (cal.selected_date == new_mdy) ? true : false;

			// Changed the highlight to false until we can determine a way for
			// the month to keep the old date value when we switch the newDate value
			// because of more days in the prior month than the month being switched
			// to:  Jan 31st => March 3rd (3 days past end of Febrary)

			cal = new calendar(cal.id, cal.date_obj, highlight);

			$('#' + 'cal' + cal.id).html(cal.write());
		}


		//	Finalize the date string
		function date_str(include_time)
		{
			var month = this.month + 1;

			if (month < 10) {
				month = '0' + month;
			}

			var day		= (this.date  < 10) 	?  '0' + this.date		: this.date;
			var minutes	= (this.minutes  < 10)	?  '0' + this.minutes	: this.minutes;

			if (time_format == 'g:i A')
			{
				var hours	= (this.hours > 12) ? this.hours - 12 : this.hours;
				var ampm	= (this.hours > 11) ? 'PM' : 'AM'
			}
			else if (time_format == 'H:i')
			{
				var hours	= this.hours;
				var ampm	= '';
			}
			else
			{
				var hours	= this.hours;
				var ampm	= '';
			}

			if (include_time == 'y')
			{
				return this.year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ' ' + ampm;
			}
			else
			{
				return this.year + '-' + month + '-' + day;
			}
		}

		</script>
		<?php

		$r = ob_get_contents();
		ob_end_clean();
		return $r;
	}
}
