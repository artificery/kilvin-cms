<?php

use Illuminate\Support\Str;
use Kilvin\Facades\Site;


if (! function_exists('kilvin_cp_url')) {
    /**
     * Takes a path and makes it into a CP URL
     *
     * @param string  $path
     * @param array $parameters @todo - Need to figure out what I want to do with these (add segments or query strings)
     * @return string
     */
    function kilvin_cp_url($path = '', $parameters = [])
    {
        // URL already?
        if (preg_match('~^https?://~', $path) && filter_var($path, FILTER_VALIDATE_URL) !== false) {
            return $path;
        }

        $path = trim($path, '/');
        $cp_path = config('cms.cp_path');

        if ($path != $cp_path && !Str::startsWith($path, $cp_path.'/')) {
            $path = '/'.$cp_path.'/'.$path;
        } else {
            $path = '/'.$path;
        }

        if (stristr(config('app.url'), 'https://')) {
            return secure_url($path, $parameters);
        } else {
            return url($path, $parameters);
        }
    }
}


if (! function_exists('remove_double_slashes')) {
    /**
     * Removes double slashes from a string, except those in URL
     *
     * @param  string  $str
     * @return string
     */
    function remove_double_slashes($str)
    {
        $str = str_replace("://", "{:SS}", $str);
        $str = str_replace(":&#47;&#47;", "{:SHSS}", $str);  // Super HTTP slashes saved!
        $str = preg_replace("#/+#", "/", $str);
        $str = preg_replace("/(&#47;)+/", "/", $str);
        $str = str_replace("&#47;/", "/", $str);
        $str = str_replace("{:SHSS}", ":&#47;&#47;", $str);
        $str = str_replace("{:SS}", "://", $str);

        return $str;
    }
}


if (! function_exists('cms_clear_caching')) {
    /**
     * Clears Caching
     *
     * @return boolean
     */
    function cms_clear_caching()
    {
        app('Illuminate\Cache\CacheManager')->flush();

        return true;
    }
}

if (! function_exists('filename_security')) {
    /**
     * Cleans out unwanted characters from a filename
     *
     * @param  string  $str
     * @return string
     */
    function filename_security($str)
    {
        $bad = [
            "../",
            "./",
            "<!--",
            "-->",
            "<",
            ">",
            "'",
            '"',
            '&',
            '$',
            '#',
            '{',
            '}',
            '[',
            ']',
            '=',
            ';',
            '?',
            '/',
            "%20",
            "%22",
            "%3c",      // <
            "%253c",    // <
            "%3e",      // >
            "%0e",      // >
            "%28",      // (
            "%29",      // )
            "%2528",    // (
            "%26",      // &
            "%24",      // $
            "%3f",      // ?
            "%3b",      // ;
            "%3d"       // =
        ];


        $str =  stripslashes(str_replace($bad, '', $str));

        return $str;
    }
}


if (! function_exists('foreign_characters')) {

    /**
     * Accented Characters conversion options
     *
     * @param  string  $str
     * @return string
     */
    function foreign_characters()
    {
        return [
            '0'    => ['°', '₀', '۰', '０'],
            '1'    => ['¹', '₁', '۱', '１'],
            '2'    => ['²', '₂', '۲', '２'],
            '3'    => ['³', '₃', '۳', '３'],
            '4'    => ['⁴', '₄', '۴', '٤', '４'],
            '5'    => ['⁵', '₅', '۵', '٥', '５'],
            '6'    => ['⁶', '₆', '۶', '٦', '６'],
            '7'    => ['⁷', '₇', '۷', '７'],
            '8'    => ['⁸', '₈', '۸', '８'],
            '9'    => ['⁹', '₉', '۹', '９'],
            'a'    => ['à', 'á', 'ả', 'ã', 'ạ', 'ă', 'ắ', 'ằ', 'ẳ', 'ẵ', 'ặ', 'â', 'ấ', 'ầ', 'ẩ', 'ẫ', 'ậ', 'ā', 'ą', 'å', 'α', 'ά', 'ἀ', 'ἁ', 'ἂ', 'ἃ', 'ἄ', 'ἅ', 'ἆ', 'ἇ', 'ᾀ', 'ᾁ', 'ᾂ', 'ᾃ', 'ᾄ', 'ᾅ', 'ᾆ', 'ᾇ', 'ὰ', 'ά', 'ᾰ', 'ᾱ', 'ᾲ', 'ᾳ', 'ᾴ', 'ᾶ', 'ᾷ', 'а', 'أ', 'အ', 'ာ', 'ါ', 'ǻ', 'ǎ', 'ª', 'ა', 'अ', 'ا', 'ａ', 'ä'],
            'b'    => ['б', 'β', 'ب', 'ဗ', 'ბ', 'ｂ'],
            'c'    => ['ç', 'ć', 'č', 'ĉ', 'ċ', 'ｃ'],
            'd'    => ['ď', 'ð', 'đ', 'ƌ', 'ȡ', 'ɖ', 'ɗ', 'ᵭ', 'ᶁ', 'ᶑ', 'д', 'δ', 'د', 'ض', 'ဍ', 'ဒ', 'დ', 'ｄ'],
            'e'    => ['é', 'è', 'ẻ', 'ẽ', 'ẹ', 'ê', 'ế', 'ề', 'ể', 'ễ', 'ệ', 'ë', 'ē', 'ę', 'ě', 'ĕ', 'ė', 'ε', 'έ', 'ἐ', 'ἑ', 'ἒ', 'ἓ', 'ἔ', 'ἕ', 'ὲ', 'έ', 'е', 'ё', 'э', 'є', 'ə', 'ဧ', 'ေ', 'ဲ', 'ე', 'ए', 'إ', 'ئ', 'ｅ'],
            'f'    => ['ф', 'φ', 'ف', 'ƒ', 'ფ', 'ｆ'],
            'g'    => ['ĝ', 'ğ', 'ġ', 'ģ', 'г', 'ґ', 'γ', 'ဂ', 'გ', 'گ', 'ｇ'],
            'h'    => ['ĥ', 'ħ', 'η', 'ή', 'ح', 'ه', 'ဟ', 'ှ', 'ჰ', 'ｈ'],
            'i'    => ['í', 'ì', 'ỉ', 'ĩ', 'ị', 'î', 'ï', 'ī', 'ĭ', 'į', 'ı', 'ι', 'ί', 'ϊ', 'ΐ', 'ἰ', 'ἱ', 'ἲ', 'ἳ', 'ἴ', 'ἵ', 'ἶ', 'ἷ', 'ὶ', 'ί', 'ῐ', 'ῑ', 'ῒ', 'ΐ', 'ῖ', 'ῗ', 'і', 'ї', 'и', 'ဣ', 'ိ', 'ီ', 'ည်', 'ǐ', 'ი', 'इ', 'ی', 'ｉ'],
            'j'    => ['ĵ', 'ј', 'Ј', 'ჯ', 'ج', 'ｊ'],
            'k'    => ['ķ', 'ĸ', 'к', 'κ', 'Ķ', 'ق', 'ك', 'က', 'კ', 'ქ', 'ک', 'ｋ'],
            'l'    => ['ł', 'ľ', 'ĺ', 'ļ', 'ŀ', 'л', 'λ', 'ل', 'လ', 'ლ', 'ｌ'],
            'm'    => ['м', 'μ', 'م', 'မ', 'მ', 'ｍ'],
            'n'    => ['ñ', 'ń', 'ň', 'ņ', 'ŉ', 'ŋ', 'ν', 'н', 'ن', 'န', 'ნ', 'ｎ'],
            'o'    => ['ó', 'ò', 'ỏ', 'õ', 'ọ', 'ô', 'ố', 'ồ', 'ổ', 'ỗ', 'ộ', 'ơ', 'ớ', 'ờ', 'ở', 'ỡ', 'ợ', 'ø', 'ō', 'ő', 'ŏ', 'ο', 'ὀ', 'ὁ', 'ὂ', 'ὃ', 'ὄ', 'ὅ', 'ὸ', 'ό', 'о', 'و', 'θ', 'ို', 'ǒ', 'ǿ', 'º', 'ო', 'ओ', 'ｏ', 'ö'],
            'p'    => ['п', 'π', 'ပ', 'პ', 'پ', 'ｐ'],
            'q'    => ['ყ', 'ｑ'],
            'r'    => ['ŕ', 'ř', 'ŗ', 'р', 'ρ', 'ر', 'რ', 'ｒ'],
            's'    => ['ś', 'š', 'ş', 'с', 'σ', 'ș', 'ς', 'س', 'ص', 'စ', 'ſ', 'ს', 'ｓ'],
            't'    => ['ť', 'ţ', 'т', 'τ', 'ț', 'ت', 'ط', 'ဋ', 'တ', 'ŧ', 'თ', 'ტ', 'ｔ'],
            'u'    => ['ú', 'ù', 'ủ', 'ũ', 'ụ', 'ư', 'ứ', 'ừ', 'ử', 'ữ', 'ự', 'û', 'ū', 'ů', 'ű', 'ŭ', 'ų', 'µ', 'у', 'ဉ', 'ု', 'ူ', 'ǔ', 'ǖ', 'ǘ', 'ǚ', 'ǜ', 'უ', 'उ', 'ｕ', 'ў', 'ü'],
            'v'    => ['в', 'ვ', 'ϐ', 'ｖ'],
            'w'    => ['ŵ', 'ω', 'ώ', 'ဝ', 'ွ', 'ｗ'],
            'x'    => ['χ', 'ξ', 'ｘ'],
            'y'    => ['ý', 'ỳ', 'ỷ', 'ỹ', 'ỵ', 'ÿ', 'ŷ', 'й', 'ы', 'υ', 'ϋ', 'ύ', 'ΰ', 'ي', 'ယ', 'ｙ'],
            'z'    => ['ź', 'ž', 'ż', 'з', 'ζ', 'ز', 'ဇ', 'ზ', 'ｚ'],
            'aa'   => ['ع', 'आ', 'آ'],
            'ae'   => ['æ', 'ǽ'],
            'ai'   => ['ऐ'],
            'ch'   => ['ч', 'ჩ', 'ჭ', 'چ'],
            'dj'   => ['ђ', 'đ'],
            'dz'   => ['џ', 'ძ'],
            'ei'   => ['ऍ'],
            'gh'   => ['غ', 'ღ'],
            'ii'   => ['ई'],
            'ij'   => ['ĳ'],
            'kh'   => ['х', 'خ', 'ხ'],
            'lj'   => ['љ'],
            'nj'   => ['њ'],
            'oe'   => ['ö', 'œ', 'ؤ'],
            'oi'   => ['ऑ'],
            'oii'  => ['ऒ'],
            'ps'   => ['ψ'],
            'sh'   => ['ш', 'შ', 'ش'],
            'shch' => ['щ'],
            'ss'   => ['ß'],
            'sx'   => ['ŝ'],
            'th'   => ['þ', 'ϑ', 'ث', 'ذ', 'ظ'],
            'ts'   => ['ц', 'ც', 'წ'],
            'ue'   => ['ü'],
            'uu'   => ['ऊ'],
            'ya'   => ['я'],
            'yu'   => ['ю'],
            'zh'   => ['ж', 'ჟ', 'ژ'],
            '(c)'  => ['©'],
            'A'    => ['Á', 'À', 'Ả', 'Ã', 'Ạ', 'Ă', 'Ắ', 'Ằ', 'Ẳ', 'Ẵ', 'Ặ', 'Â', 'Ấ', 'Ầ', 'Ẩ', 'Ẫ', 'Ậ', 'Å', 'Ā', 'Ą', 'Α', 'Ά', 'Ἀ', 'Ἁ', 'Ἂ', 'Ἃ', 'Ἄ', 'Ἅ', 'Ἆ', 'Ἇ', 'ᾈ', 'ᾉ', 'ᾊ', 'ᾋ', 'ᾌ', 'ᾍ', 'ᾎ', 'ᾏ', 'Ᾰ', 'Ᾱ', 'Ὰ', 'Ά', 'ᾼ', 'А', 'Ǻ', 'Ǎ', 'Ａ', 'Ä'],
            'B'    => ['Б', 'Β', 'ब', 'Ｂ'],
            'C'    => ['Ç', 'Ć', 'Č', 'Ĉ', 'Ċ', 'Ｃ'],
            'D'    => ['Ď', 'Ð', 'Đ', 'Ɖ', 'Ɗ', 'Ƌ', 'ᴅ', 'ᴆ', 'Д', 'Δ', 'Ｄ'],
            'E'    => ['É', 'È', 'Ẻ', 'Ẽ', 'Ẹ', 'Ê', 'Ế', 'Ề', 'Ể', 'Ễ', 'Ệ', 'Ë', 'Ē', 'Ę', 'Ě', 'Ĕ', 'Ė', 'Ε', 'Έ', 'Ἐ', 'Ἑ', 'Ἒ', 'Ἓ', 'Ἔ', 'Ἕ', 'Έ', 'Ὲ', 'Е', 'Ё', 'Э', 'Є', 'Ə', 'Ｅ'],
            'F'    => ['Ф', 'Φ', 'Ｆ'],
            'G'    => ['Ğ', 'Ġ', 'Ģ', 'Г', 'Ґ', 'Γ', 'Ｇ'],
            'H'    => ['Η', 'Ή', 'Ħ', 'Ｈ'],
            'I'    => ['Í', 'Ì', 'Ỉ', 'Ĩ', 'Ị', 'Î', 'Ï', 'Ī', 'Ĭ', 'Į', 'İ', 'Ι', 'Ί', 'Ϊ', 'Ἰ', 'Ἱ', 'Ἳ', 'Ἴ', 'Ἵ', 'Ἶ', 'Ἷ', 'Ῐ', 'Ῑ', 'Ὶ', 'Ί', 'И', 'І', 'Ї', 'Ǐ', 'ϒ', 'Ｉ'],
            'J'    => ['Ｊ'],
            'K'    => ['К', 'Κ', 'Ｋ'],
            'L'    => ['Ĺ', 'Ł', 'Л', 'Λ', 'Ļ', 'Ľ', 'Ŀ', 'ल', 'Ｌ'],
            'M'    => ['М', 'Μ', 'Ｍ'],
            'N'    => ['Ń', 'Ñ', 'Ň', 'Ņ', 'Ŋ', 'Н', 'Ν', 'Ｎ'],
            'O'    => ['Ó', 'Ò', 'Ỏ', 'Õ', 'Ọ', 'Ô', 'Ố', 'Ồ', 'Ổ', 'Ỗ', 'Ộ', 'Ơ', 'Ớ', 'Ờ', 'Ở', 'Ỡ', 'Ợ', 'Ø', 'Ō', 'Ő', 'Ŏ', 'Ο', 'Ό', 'Ὀ', 'Ὁ', 'Ὂ', 'Ὃ', 'Ὄ', 'Ὅ', 'Ὸ', 'Ό', 'О', 'Θ', 'Ө', 'Ǒ', 'Ǿ', 'Ｏ', 'Ö'],
            'P'    => ['П', 'Π', 'Ｐ'],
            'Q'    => ['Ｑ'],
            'R'    => ['Ř', 'Ŕ', 'Р', 'Ρ', 'Ŗ', 'Ｒ'],
            'S'    => ['Ş', 'Ŝ', 'Ș', 'Š', 'Ś', 'С', 'Σ', 'Ｓ'],
            'T'    => ['Ť', 'Ţ', 'Ŧ', 'Ț', 'Т', 'Τ', 'Ｔ'],
            'U'    => ['Ú', 'Ù', 'Ủ', 'Ũ', 'Ụ', 'Ư', 'Ứ', 'Ừ', 'Ử', 'Ữ', 'Ự', 'Û', 'Ū', 'Ů', 'Ű', 'Ŭ', 'Ų', 'У', 'Ǔ', 'Ǖ', 'Ǘ', 'Ǚ', 'Ǜ', 'Ｕ', 'Ў', 'Ü'],
            'V'    => ['В', 'Ｖ'],
            'W'    => ['Ω', 'Ώ', 'Ŵ', 'Ｗ'],
            'X'    => ['Χ', 'Ξ', 'Ｘ'],
            'Y'    => ['Ý', 'Ỳ', 'Ỷ', 'Ỹ', 'Ỵ', 'Ÿ', 'Ῠ', 'Ῡ', 'Ὺ', 'Ύ', 'Ы', 'Й', 'Υ', 'Ϋ', 'Ŷ', 'Ｙ'],
            'Z'    => ['Ź', 'Ž', 'Ż', 'З', 'Ζ', 'Ｚ'],
            'AE'   => ['Æ', 'Ǽ'],
            'Ch'   => ['Ч'],
            'Dj'   => ['Ђ'],
            'Dz'   => ['Џ'],
            'Gx'   => ['Ĝ'],
            'Hx'   => ['Ĥ'],
            'Ij'   => ['Ĳ'],
            'Jx'   => ['Ĵ'],
            'Kh'   => ['Х'],
            'Lj'   => ['Љ'],
            'Nj'   => ['Њ'],
            'Oe'   => ['Œ'],
            'Ps'   => ['Ψ'],
            'Sh'   => ['Ш'],
            'Shch' => ['Щ'],
            'Ss'   => ['ẞ'],
            'Th'   => ['Þ'],
            'Ts'   => ['Ц'],
            'Ya'   => ['Я'],
            'Yu'   => ['Ю'],
            'Zh'   => ['Ж']
        ];
    }
}


if (! function_exists('url_title_javascript')) {

    /**
     * Javascript to convert Accented Chars to a url_title friendly version
     *
     * @param  string  $str
     * @return string
     */
    function url_title_javascript($separator, $prefix = '')
    {
        $foreign_replace = '';

        foreach(foreign_characters() as $to => $from_array)
        {
            foreach($from_array as $from) {
                $foreign_replace .= "if (c == '$from') {NewTextTemp += '$to'; continue;}\n\t\t\t\t";
            }
        }

        return <<<EOT

<script type="text/javascript">

    // ------------------------------------
    //  Live URL Title Function
    // ------------------------------------

    function liveUrlTitle(start, end)
    {
        $(end).val(covertToUrlTitle($(start).val()));
    }

    function covertToUrlTitle(text)
    {
        text = text.toLowerCase();
        var separator = "{$separator}";

        // Foreign Character Attempt

        var NewTextTemp = '';
        for(var pos=0; pos < text.length; pos++)
        {
            var code = text.charCodeAt(pos);

            // White Space
            if (code >= 32 && code < 128) {
                NewTextTemp += text.charAt(pos);
                continue;
            }

            // ASCII
            if (code >= 32 && code < 128) {
                NewTextTemp += text.charAt(pos);
                continue;
            }

            var c = text.charAt(pos);
            {$foreign_replace}
        }

        var multiReg = new RegExp(separator + '{2,}', 'g');

        text = NewTextTemp;

        text = text.replace('/<(.*?)>/g', '');
        text = text.replace(/\s+/g, separator);
        text = text.replace(/\//g, separator);
        text = text.replace(/[^a-z0-9\-\._]/g,'');
        text = text.replace(/\+/g, separator);
        text = text.replace(multiReg, separator);
        text = text.replace(/-$/g,'');
        text = text.replace(/_$/g,'');
        text = text.replace(/^_/g,'');
        text = text.replace(/^-/g,'');
        text = text.replace(/\.+$/g,'');

        return ("{$prefix}" + text).substring(0,75);
    }

</script>


EOT;
    }
}

if (! function_exists('svg_icon_gear')) {
    /**
     * Clears Caching
     *
     * @return boolean
     */
    function svg_icon_gear()
    {
        return '<svg
            xmlns:dc="http://purl.org/dc/elements/1.1/"
            xmlns:cc="http://creativecommons.org/ns#"
            xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
            xmlns:svg="http://www.w3.org/2000/svg"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 -256 1792 1792"
            version="1.1"
        >
  <g transform="matrix(1,0,0,-1,121.49153,1285.4237)" id="g3027">
    <path d="m 1024,640 q 0,106 -75,181 -75,75 -181,75 -106,0 -181,-75 -75,-75 -75,-181 0,-106 75,-181 75,-75 181,-75 106,0 181,75 75,75 75,181 z m 512,109 V 527 q 0,-12 -8,-23 -8,-11 -20,-13 l -185,-28 q -19,-54 -39,-91 35,-50 107,-138 10,-12 10,-25 0,-13 -9,-23 -27,-37 -99,-108 -72,-71 -94,-71 -12,0 -26,9 l -138,108 q -44,-23 -91,-38 -16,-136 -29,-186 -7,-28 -36,-28 H 657 q -14,0 -24.5,8.5 Q 622,-111 621,-98 L 593,86 q -49,16 -90,37 L 362,16 Q 352,7 337,7 323,7 312,18 186,132 147,186 q -7,10 -7,23 0,12 8,23 15,21 51,66.5 36,45.5 54,70.5 -27,50 -41,99 L 29,495 Q 16,497 8,507.5 0,518 0,531 v 222 q 0,12 8,23 8,11 19,13 l 186,28 q 14,46 39,92 -40,57 -107,138 -10,12 -10,24 0,10 9,23 26,36 98.5,107.5 72.5,71.5 94.5,71.5 13,0 26,-10 l 138,-107 q 44,23 91,38 16,136 29,186 7,28 36,28 h 222 q 14,0 24.5,-8.5 Q 914,1391 915,1378 l 28,-184 q 49,-16 90,-37 l 142,107 q 9,9 24,9 13,0 25,-10 129,-119 165,-170 7,-8 7,-22 0,-12 -8,-23 -15,-21 -51,-66.5 -36,-45.5 -54,-70.5 26,-50 41,-98 l 183,-28 q 13,-2 21,-12.5 8,-10.5 8,-23.5 z" id="path3029" inkscape:connector-curvature="0" style="fill:currentColor"/>
  </g>
</svg>';
    }
}

if (! function_exists('parsedown')) {
    /**
     * Clears Caching
     *
     * @return boolean
     */
    function parsedown($str)
    {
        return resolve('parsedown')->text($str);
    }
}


if (! function_exists('rot13_javascript')) {

    /**
     * Returns a rot13 converted string with JavaScript to decode it
     *
     * @link http://snipplr.com/view/6037/
     * @link https://en.wikipedia.org/wiki/ROT13
     *
     * @param string $str The string to convert
     * @return string
     */
    function rot13_javascript($str) {
        $rotated = str_replace('"','\"',str_rot13($str));
        return <<<EOF
             <script type="text/javascript">
            /*<![CDATA[*/
            document.write("$rotated".replace(/[a-zA-Z]/g, function(c){return String.fromCharCode((c<="Z"?90:122)>=(c=c.charCodeAt(0)+13)?c:c-26);}));
            /*]]>*/
            </script>

EOF;

    }
}


if (! function_exists('escape_attribute')) {
    /**
    * Prep String for Form Usage
    *
    * @param string $str
    * @return string
    */
    function escape_attribute($str = '')
    {
        if ($str === '') {
            return '';
        }

        $str = htmlspecialchars($str);
        $str = str_replace("'", "&#39;", $str);

        return $str;
    }
}



if (! function_exists('convert_quotes')) {
    /**
    * Convert single and double quotes to entites
    *
    * @param string $str
    * @return string
    */
    function convert_quotes($str)
    {
        return str_replace(["\'",'"'], ["&#39;","&quot;"], $str);
    }
}


if (! function_exists('create_url_title')) {
    /**
    * Create URL Title - PHP Version
    *
    * Normally, a user will provide us a urltitle and we will just insure it is
    * fully valid with this. In those cases, we allow uppercased alpha characters
    *
    * However, if one is not provided, we attempt to create one and we make it lowercase
    *
    * @param string $str
    * @return string
    */
    function create_url_title($str, $lowercase = false)
    {
        if (function_exists('mb_convert_encoding'))
        {
            $str = mb_convert_encoding($str, 'UTF-8', 'auto');
        }
        elseif(function_exists('iconv') AND ($iconvstr = @iconv('', 'UTF-8', $str)) !== false)
        {
            $str = $iconvstr;
        }
        else
        {
            $str = utf8_decode($str);
        }

        if ($lowercase === true) {
            $str = mb_strtolower($str, 'UTF-8');
        }

        $str = convert_accented_characters(strip_tags($str));

        // Use dash or underscore as separator
        $replace = (Site::config('word_separator') == 'dash') ? '-' : '_';

        $trans = [
            '&\#\d+?;'                  => '',
            '&\S+?;'                    => '',
            '\s+'                       => $replace,
            '[^a-zA-Z0-9\-\._]'         => '',
            preg_quote($replace).'+'    => $replace,
            preg_quote($replace).'$'    => $replace,
            '^'.preg_quote($replace )   => $replace,
            '\.+$'                      => ''
        ];

        foreach ($trans as $key => $val) {
            $str = preg_replace("#".$key."#i", $val, $str);
        }

        $str = trim(stripslashes($str));

        return $str;
    }
}


if (! function_exists('convert_accented_characters')) {
    /**
    * Convert Accented Characters to Unaccented Equivalents
    *
    * @param string $str
    * @return string
    */
    function convert_accented_characters($str)
    {
        $foreign_characters = foreign_characters();

        $find = $replace = [];

        foreach(foreign_characters() as $new => $chars) {
            foreach($chars as $old) {
                $find[]    = $old;
                $replace[] = $new;
            }
        }

        return str_replace($find, $replace, $str);
    }
}
