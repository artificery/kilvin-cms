<?php

use Illuminate\Support\Str;
use Kilvin\Facades\Site;


if (! function_exists('kilvinCpUrl')) {
    /**
     * Takes a path and makes it into a CP URL
     *
     * @param string  $path
     * @param array $parameters @todo - Need to figure out what I want to do with these (add segments or query strings)
     * @return string
     */
    function kilvinCpUrl($path = '', $parameters = [])
    {
        // URL already?
        if (preg_match('~^https?://~', $path) && filter_var($path, FILTER_VALIDATE_URL) !== false) {
            return $path;
        }

        $path = trim($path, '/');
        $cp_path = config('kilvin.cp_path');

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


if (! function_exists('removeDoubleSlashes')) {
    /**
     * Removes double slashes from a string, except those in URL
     *
     * @param  string  $str
     * @return string
     */
    function removeDoubleSlashes($str)
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


if (! function_exists('cmsClearCaching')) {
    /**
     * Clears Caching
     *
     * @return boolean
     */
    function cmsClearCaching()
    {
        app('Illuminate\Cache\CacheManager')->flush();

        return true;
    }
}

if (! function_exists('filenameSecurity')) {
    /**
     * Cleans out unwanted characters from a filename
     *
     * @param  string  $str
     * @return string
     */
    function filenameSecurity($str)
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


if (! function_exists('foreignCharacters')) {

    /**
     * Accented Characters conversion options
     *
     * @param  string  $str
     * @return string
     */
    function foreignCharacters()
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


if (! function_exists('urlTitleJavascript')) {

    /**
     * Javascript to convert Accented Chars to a url_title friendly version
     *
     * @param  string  $str
     * @return string
     */
    function urlTitleJavascript($separator, $prefix = '')
    {
        $foreign_replace = '';

        foreach(foreignCharacters() as $to => $from_array)
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


if (! function_exists('encodeEmailJs')) {

    /**
     * Returns a rot13 converted string with JavaScript to decode it
     *
     * @link http://snipplr.com/view/6037/
     * @link https://en.wikipedia.org/wiki/ROT13
     *
     * @param string $str The string to convert
     * @return string
     */
    function encodeEmailJs($str) {
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


if (! function_exists('escapeAttribute')) {
    /**
    * Prep String for Form Usage
    *
    * @param string $str
    * @return string
    */
    function escapeAttribute($str = '')
    {
        if ($str === '') {
            return '';
        }

        $str = htmlspecialchars($str);
        $str = str_replace("'", "&#39;", $str);

        return $str;
    }
}

if (! function_exists('createUrlTitle')) {
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
    function createUrlTitle($str, $lowercase = false)
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

        $str = convertAccentedCharacters(strip_tags($str));

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


if (! function_exists('convertAccentedCharacters')) {
    /**
    * Convert Accented Characters to Unaccented Equivalents
    *
    * @param string $str
    * @return string
    */
    function convertAccentedCharacters($str)
    {
        $foreign_characters = foreignCharacters();

        $find = $replace = [];

        foreach(foreignCharacters() as $new => $chars) {
            foreach($chars as $old) {
                $find[]    = $old;
                $replace[] = $new;
            }
        }

        return str_replace($find, $replace, $str);
    }
}


if (! function_exists('outputThemeFile')) {
    /**
     * Loads up a Theme File from Controller Request
     *
     * @param string
     * @param string
     * @return \Illuminate\Http\Response
     */
    function outputThemeFile($path, $content_type)
    {
        if (file_exists($path)) {

            $lifetime = 60*60*24*365;

            $handler = new \Symfony\Component\HttpFoundation\File\File($path);

            $file_time = $handler->getMTime();
            $header_content_length = $handler->getSize();
            $header_etag = md5($file_time . $path);
            $header_last_modified = gmdate('r', $file_time);
            $header_expires = gmdate('r', $file_time + $lifetime);

            $headers = [
                'Last-Modified' => $header_last_modified,
                'Cache-Control' => 'public',
                'Expires' => $header_expires,
                'Pragma' => 'public',
                'Etag' => $header_etag
            ];

            /**
             * Is the resource cached?
             */
            $h1 = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $header_last_modified;
            $h2 = isset($_SERVER['HTTP_IF_NONE_MATCH']) && str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) == $header_etag;

            if ($h1 || $h2) {
                return response()->make('', 304, $headers);
            }

            $headers = array_merge($headers, [
                'Content-Type' => $content_type,
                'Content-Length' => $header_content_length
            ]);

            return response()->make(file_get_contents($path), 200, $headers);
        }

        abort(404);
    }
}
