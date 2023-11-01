<?php

/**
 * Output content
 * @package framework
 * @subpackage output
 */

/**
 * Base class that controls how data is output
 * @abstract
 */
abstract class Hm_Output {

    /**
     * Extended classes must override this method to output content
     * @param mixed $content data to output
     * @param array $headers headers to send
     * @return void
     */
    abstract protected function output_content($content, $headers);

    /**
     * Wrapper around extended class output_content() calls
     * @param mixed $response data to output
     * @param array $input raw module data
     * @return void
     */
    public function send_response($response, $input=array()) {
        if (array_key_exists('http_headers', $input)) {
            $this->output_content($response, $input['http_headers']);
        }
        else {
            $this->output_content($response, array());
        }
    }
}

/**
 * Output request responses using HTTP
 */
class Hm_Output_HTTP extends Hm_Output {

    /**
     * Send HTTP headers
     * @param array $headers headers to send
     * @return void
     */
    protected function output_headers($headers) {
        foreach ($headers as $name => $value) {
            Hm_Functions::header($name.': '.$value);
        }
    }

    /**
     * Send response content to the browser
     * @param mixed $content data to send
     * @param array $headers HTTP headers to set
     * @return void
     */
    protected function output_content($content, $headers=array()) {
        $this->output_headers($headers);
        ob_end_clean();
        echo $content;
    }
}

/**
 * Data URLs for icons used by the interface.
 */
class Hm_Image_Sources {

    public static function __callStatic(string $method, array $parameters)
    // This method adds a way to customize the svg icons according to your theme by adding the preferable icon color when calling Hm_Image_Sources
    {
        if (!property_exists('Hm_Image_Sources',$method)) {
            return "";
        }
        return str_replace("width%3D%228%22%20height%3D%228%22%20",'fill%3D%22'.urlencode($parameters[0]).'%22%20width%3D%228%22%20height%3D%228%22%20',Hm_Image_Sources::${$method});
    }

    public static $power = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3%200v4h1v-4h-1zm-1.281%201.438l-.375.313c-.803.64-1.344%201.634-1.344%202.75%200%201.929%201.571%203.5%203.5%203.5s3.5-1.571%203.5-3.5c0-1.116-.529-2.11-1.344-2.75l-.375-.313-.625.781.375.313c.585.46.969%201.165.969%201.969%200%201.391-1.109%202.5-2.5%202.5s-2.5-1.109-2.5-2.5c0-.804.361-1.509.938-1.969l.406-.313-.625-.781z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $home = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200l-4%203h1v4h2v-2h2v2h2v-4.031l1%20.031-4-3z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $box = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v1h8v-1h-8zm0%202v5.906c0%20.06.034.094.094.094h7.813c.06%200%20.094-.034.094-.094v-5.906h-2.969v1.031h-2.031v-1.031h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $env_closed = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%201v1l4%202%204-2v-1h-8zm0%202v4h8v-4l-4%202-4-2z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $env_open = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200l-4%202v6h8v-6l-4-2zm0%201.125l3%201.5v1.875l-3%201.5-3-1.5v-1.875l3-1.5zm-2%201.875v1l2%201%202-1v-1h-4z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $star = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v8h1v-8h-1zm2%200v4h2v1h4l-2-1.969%202-2.031h-3v-1h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $globe = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.21%200-4%201.79-4%204s1.79%204%204%204%204-1.79%204-4-1.79-4-4-4zm0%201c.333%200%20.637.086.938.188-.214.197-.45.383-.406.563.04.18.688.13.688.5%200%20.27-.425.346-.125.656.35.35-.636.978-.656%201.438-.03.83.841.969%201.531.969.424%200%20.503.195.469.438-.546.758-1.438%201.25-2.438%201.25-.378%200-.729-.09-1.063-.219.224-.442-.313-1.344-.781-1.625-.226-.226-.689-.114-.969-.219-.092-.271-.178-.545-.188-.844.031-.05.081-.094.156-.094.19%200%20.454.374.594.344.18-.04-.742-1.313-.313-1.563.2-.12.609.394.469-.156-.12-.51.366-.276.656-.406.26-.11.455-.414.125-.594l-.219-.188c.45-.27.972-.438%201.531-.438zm2.313%201.094c.184.222.323.481.438.75l-.188.219c-.29.27-.327-.212-.438-.313-.13-.11-.638.025-.688-.125-.077-.181.499-.418.875-.531z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $doc = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v8h7v-4h-4v-4h-3zm4%200v3h3l-3-3zm-3%202h1v1h-1v-1zm0%202h1v1h-1v-1zm0%202h4v1h-4v-1z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $monitor = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M.344%200a.5.5%200%200%200-.344.5v5a.5.5%200%200%200%20.5.5h2.5v1h-1c-.55%200-1%20.45-1%201h6c0-.55-.45-1-1-1h-1v-1h2.5a.5.5%200%200%200%20.5-.5v-5a.5.5%200%200%200-.5-.5h-7a.5.5%200%200%200-.094%200%20.5.5%200%200%200-.063%200zm.656%201h6v4h-6v-4z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $cog = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3.5%200l-.5%201.188-.281.125-1.188-.5-.719.719.5%201.188-.125.281-1.188.5v1l1.188.5.125.313-.5%201.156.719.719%201.188-.5.281.125.5%201.188h1l.5-1.188.281-.125%201.188.5.719-.719-.5-1.188.125-.281%201.188-.5v-1l-1.188-.5-.125-.281.469-1.188-.688-.719-1.188.5-.281-.125-.5-1.188h-1zm.5%202.5c.83%200%201.5.67%201.5%201.5s-.67%201.5-1.5%201.5-1.5-.67-1.5-1.5.67-1.5%201.5-1.5z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $people = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M5.5%200c-.51%200-.949.355-1.219.875.45.54.719%201.275.719%202.125%200%20.29-.034.574-.094.844.18.11.374.156.594.156.83%200%201.5-.9%201.5-2s-.67-2-1.5-2zm-3%201c-.828%200-1.5.895-1.5%202s.672%202%201.5%202%201.5-.895%201.5-2-.672-2-1.5-2zm4.75%203.156c-.43.51-1.018.824-1.688.844.27.38.438.844.438%201.344v.656h2v-1.656c0-.52-.31-.968-.75-1.188zm-6.5%201c-.44.22-.75.668-.75%201.188v1.656h5v-1.656c0-.52-.31-.968-.75-1.188-.44.53-1.06.844-1.75.844s-1.31-.314-1.75-.844z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $caret = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M2%200v8l4-4-4-4z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $folder = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v2h8v-1h-5v-1h-3zm0%203v4.5c0%20.28.22.5.5.5h7c.28%200%20.5-.22.5-.5v-4.5h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $chevron = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M1.5%201l-1.5%201.5%204%204%204-4-1.5-1.5-2.5%202.5-2.5-2.5z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $check = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6.406%201l-.719.688-2.781%202.781-.781-.781-.719-.688-1.406%201.406.688.719%201.5%201.5.719.688.719-.688%203.5-3.5.688-.719-1.406-1.406z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $refresh = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $big_caret_left = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6%200l-4%204%204%204v-8z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $search = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3.5%200c-1.927%200-3.5%201.573-3.5%203.5s1.573%203.5%203.5%203.5c.592%200%201.166-.145%201.656-.406a1%201%200%200%200%20.125.125l1%201a1.016%201.016%200%201%200%201.438-1.438l-1-1a1%201%200%200%200-.156-.125c.266-.493.438-1.059.438-1.656%200-1.927-1.573-3.5-3.5-3.5zm0%201c1.387%200%202.5%201.113%202.5%202.5%200%20.661-.241%201.273-.656%201.719l-.031.031a1%201%200%200%200-.125.125c-.442.397-1.043.625-1.688.625-1.387%200-2.5-1.113-2.5-2.5s1.113-2.5%202.5-2.5z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $spreadsheet = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M.75%200c-.402%200-.75.348-.75.75v5.5c0%20.402.348.75.75.75h6.5c.402%200%20.75-.348.75-.75v-5.5c0-.402-.348-.75-.75-.75h-6.5zm.25%201h1v1h-1v-1zm2%200h4v1h-4v-1zm-2%202h1v1h-1v-1zm2%200h4v1h-4v-1zm-2%202h1v1h-1v-1zm2%200h4v1h-4v-1z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $info = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M5%200c-.552%200-1%20.448-1%201s.448%201%201%201%201-.448%201-1-.448-1-1-1zm-1.5%202.5c-.83%200-1.5.67-1.5%201.5h1c0-.28.22-.5.5-.5s.5.22.5.5-1%201.64-1%202.5c0%20.86.67%201.5%201.5%201.5s1.5-.67%201.5-1.5h-1c0%20.28-.22.5-.5.5s-.5-.22-.5-.5c0-.36%201-1.84%201-2.5%200-.81-.67-1.5-1.5-1.5z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $bug = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3.5%200c-1.19%200-1.978%201.69-1.188%202.5l-.281.219-1.313-.656a.5.5%200%200%200-.344-.063.5.5%200%200%200-.094.938l1.156.563c-.09.156-.186.328-.25.5h-.688a.5.5%200%200%200-.094%200%20.502.502%200%201%200%20.094%201h.5c0%20.227.023.445.063.656l-.781.406a.5.5%200%201%200%20.438.875l.656-.344c.245.46.59.844%201%201.094.35-.19.625-.439.625-.719v-1.438a.5.5%200%200%200%200-.094v-.813a.5.5%200%200%200%200-.219c.045-.231.254-.406.5-.406.28%200%20.5.22.5.5v.875a.5.5%200%200%200%200%20.094v.063a.5.5%200%200%200%200%20.094v1.344c0%20.27.275.497.625.688.41-.245.755-.604%201-1.063l.656.344a.5.5%200%201%200%20.438-.875l-.781-.406c.04-.211.063-.429.063-.656h.5a.5.5%200%201%200%200-1h-.688c-.064-.172-.16-.344-.25-.5l1.156-.563a.5.5%200%200%200-.313-.938.5.5%200%200%200-.125.063l-1.313.656-.281-.219c.78-.83.003-2.5-1.188-2.5z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $code = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M5%201l-3%206h1l3-6h-1zm-4%201l-1%202%201%202h1l-1-2%201-2h-1zm5%200l1%202-1%202h1l1-2-1-2h-1z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $person = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-1.105%200-2%201.119-2%202.5s.895%202.5%202%202.5%202-1.119%202-2.5-.895-2.5-2-2.5zm-2.094%205c-1.07.04-1.906.92-1.906%202v1h8v-1c0-1.08-.836-1.96-1.906-2-.54.61-1.284%201-2.094%201-.81%200-1.554-.39-2.094-1z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $rss = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M1%200v1c3.32%200%206%202.68%206%206h1c0-3.86-3.14-7-7-7zm0%202v1c2.221%200%204%201.779%204%204h1c0-2.759-2.241-5-5-5zm0%202v1c1.109%200%202%20.891%202%202h1c0-1.651-1.349-3-3-3zm0%202c-.552%200-1%20.448-1%201s.448%201%201%201%201-.448%201-1-.448-1-1-1z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $rss_alt = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v2c3.331%200%206%202.669%206%206h2c0-4.409-3.591-8-8-8zm0%203v2c1.67%200%203%201.33%203%203h2c0-2.75-2.25-5-5-5zm0%203v2h2c0-1.11-.89-2-2-2z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $caret_left = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6%200l-4%204%204%204v-8z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $caret_right = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M2%200v8l4-4-4-4z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $calendar = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v2h7v-2h-7zm0%203v4.906c0%20.06.034.094.094.094h6.813c.06%200%20.094-.034.094-.094v-4.906h-7zm1%201h1v1h-1v-1zm2%200h1v1h-1v-1zm2%200h1v1h-1v-1zm-4%202h1v1h-1v-1zm2%200h1v1h-1v-1z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $circle_check = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.21%200-4%201.79-4%204s1.79%204%204%204%204-1.79%204-4-1.79-4-4-4zm2%201.781l.719.719-3.219%203.219-1.719-1.719.719-.719%201%201%202.5-2.5z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $circle_x = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.21%200-4%201.79-4%204s1.79%204%204%204%204-1.79%204-4-1.79-4-4-4zm-1.5%201.781l1.5%201.5%201.5-1.5.719.719-1.5%201.5%201.5%201.5-.719.719-1.5-1.5-1.5%201.5-.719-.719%201.5-1.5-1.5-1.5.719-.719z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $key = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M5.5%200c-1.38%200-2.5%201.12-2.5%202.5%200%20.16.033.297.063.438l-3.063%203.063v2h3v-2h2v-1l.063-.063c.14.03.277.063.438.063%201.38%200%202.5-1.12%202.5-2.5s-1.12-2.5-2.5-2.5zm.5%201c.55%200%201%20.45%201%201s-.45%201-1%201-1-.45-1-1%20.45-1%201-1z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $save = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3%200v3h-2l3%203%203-3h-2v-3h-2zm-3%207v1h8v-1h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $plus = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3%200v3h-3v2h3v3h2v-3h3v-2h-3v-3h-2z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $minus = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%203v2h8v-2h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $book = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M1%200l-.188.031c-.39.08-.701.391-.781.781l-.031.188v5.5c0%20.83.67%201.5%201.5%201.5h5.5v-1h-5.5c-.28%200-.5-.22-.5-.5s.22-.5.5-.5h5.5v-5.5c0-.28-.22-.5-.5-.5h-.5v3l-1-1-1%201v-3h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $paperclip = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M5%200c-.514%200-1.021.201-1.406.594l-2.781%202.719c-1.07%201.07-1.07%202.805%200%203.875%201.07%201.07%202.805%201.07%203.875%200l1.25-1.25-.688-.688-.906.875-.344.375c-.69.69-1.81.69-2.5%200-.682-.682-.668-1.778%200-2.469l2.781-2.719v-.031c.389-.395%201.037-.4%201.438%200%20.388.381.378%201.006%200%201.406l-2.5%202.469c-.095.095-.28.095-.375%200-.095-.095-.095-.28%200-.375l.375-.344.594-.625-.688-.688-.875.875-.094.094c-.485.485-.485%201.265%200%201.75.485.485%201.265.485%201.75%200l2.5-2.438c.78-.78.785-2.041%200-2.813-.39-.39-.893-.594-1.406-.594z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $tags = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%201v2l3%203%201.5-1.5.5-.5-2-2-1-1h-2zm3.406%200l3%203-1.188%201.219.781.781%202-2-3-3h-1.594zm-1.906%201c.28%200%20.5.22.5.5s-.22.5-.5.5-.5-.22-.5-.5.22-.5.5-.5z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $tag = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v3l5%205%203-3-5-5h-3zm2%201c.55%200%201%20.45%201%201s-.45%201-1%201-1-.45-1-1%20.45-1%201-1z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $history = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v1h.5c.28%200%20.5.22.5.5v4c0%20.28-.22.5-.5.5h-.5v1h3v-1h-.5c-.28%200-.5-.22-.5-.5v-1.5h3v1.5c0%20.28-.22.5-.5.5h-.5v1h3v-1h-.5c-.28%200-.5-.22-.5-.5v-4c0-.28.22-.5.5-.5h.5v-1h-3v1h.5c.28%200%20.5.22.5.5v1.5h-3v-1.5c0-.28.22-.5.5-.5h.5v-1h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $sent = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M5%200v2c-4%200-5%202.05-5%205%20.52-1.98%202-3%204-3h1v2l3-3.156-3-2.844z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $unlocked = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-1.099%200-2%20.901-2%202h1c0-.561.439-1%201-1%20.561%200%201%20.439%201%201v2h-4v4h6v-4h-1v-2c0-1.099-.901-2-2-2z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $lock = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-1.099%200-2%20.901-2%202v1h-1v4h6v-4h-1v-1c0-1.099-.901-2-2-2zm0%201c.561%200%201%20.439%201%201v1h-2v-1c0-.561.439-1%201-1z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $audio = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M1.188%201c-.734.722-1.188%201.748-1.188%202.844%200%201.095.454%202.09%201.188%202.813l.688-.719c-.546-.538-.875-1.269-.875-2.094s.329-1.587.875-2.125l-.688-.719zm5.625%200l-.688.719c.552.552.875%201.289.875%202.125%200%20.836-.327%201.554-.875%202.094l.688.719c.732-.72%201.188-1.708%201.188-2.813%200-1.104-.459-2.115-1.188-2.844zm-4.219%201.406c-.362.362-.594.889-.594%201.438%200%20.548.232%201.045.594%201.406l.688-.719c-.178-.178-.281-.416-.281-.688%200-.272.103-.54.281-.719l-.688-.719zm2.813%200l-.688.719c.183.183.281.434.281.719s-.099.505-.281.688l.688.719c.357-.357.594-.851.594-1.406%200-.555-.236-1.08-.594-1.438z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $camera = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4.094%200c-.06%200-.105.044-.125.094l-.938%201.813c-.02.05-.065.094-.125.094h-1.406c-.83%200-1.5.67-1.5%201.5v4.406c0%20.06.034.094.094.094h7.813c.06%200%20.094-.034.094-.094v-5.813c0-.06-.034-.094-.094-.094h-.813c-.06%200-.105-.044-.125-.094l-.938-1.813c-.02-.05-.065-.094-.125-.094h-1.813zm-2.594%203c.28%200%20.5.22.5.5s-.22.5-.5.5-.5-.22-.5-.5.22-.5.5-.5zm3.5%200c1.1%200%202%20.9%202%202s-.9%202-2%202-2-.9-2-2%20.9-2%202-2zm0%201c-.55%200-1%20.45-1%201s.45%201%201%201%201-.45%201-1-.45-1-1-1z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $menu = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%201v1h8v-1h-8zm0%202.969v1h8v-1h-8zm0%203v1h8v-1h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $pencil = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6%200l-1%201%202%202%201-1-2-2zm-2%202l-4%204v2h2l4-4-2-2z%22%20%2F%3E%0A%3C%2Fsvg%3E';

    public static $w = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4AgKARwCjOZMwQAAAB1pVFh0Q29tbWVudAAAAAAAQ3JlYXRlZCB3aXRoIEdJTVBkLmUHAAAA2UlEQVQ4y+3ToUrDURTH8Q/MwdCFMbSIj7BgHWgSBnsAGZjEbPYVfICFubSwtGbwH8xisRnWBcMQFLUpglrO5PBn7gHECzfc7/3xO4ffuZf/BV8L9lbc7cX5BbVg2yWtKp4S2Enmo8T3Ez9Akbs4S8JBsBpeEz9P+osw+Vm7SfgYXfUwTfwdTazjGWtQCYN7HKKBVdzgCENsRiYV3KGFN0zKYZ6mapdRpY7jxK9wje6iabRK6Y6Db+Aj2CcesPLbSG+TQSfxIvH+sjdxEqJZymc+trlB+4/9g28CnkONSGOI8gAAAABJRU5ErkJggg==';
    public static $save_reminder = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4AgSFQseE+bgxAAAAB1pVFh0Q29tbWVudAAAAAAAQ3JlYXRlZCB3aXRoIEdJTVBkLmUHAAAAS0lEQVQ4y6WTSQoAMAgDk/z/z+21lK6ON5UZEIklNdXLIbAkhcBVgccmBP4VeDUMgV8FPi1D4JvAL7eFwDuBf/4aAs8CV0NB0sirA+jtAijusTaJAAAAAElFTkSuQmCC';

    public static $edit = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6%200l-1%201%202%202%201-1-2-2zm-2%202l-4%204v2h2l4-4-2-2z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $three_dot = 'data:image/svg+xml;base64,PCFET0NUWVBFIHN2ZyBQVUJMSUMgIi0vL1czQy8vRFREIFNWRyAxLjEvL0VOIiAiaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkIj4KDTwhLS0gVXBsb2FkZWQgdG86IFNWRyBSZXBvLCB3d3cuc3ZncmVwby5jb20sIFRyYW5zZm9ybWVkIGJ5OiBTVkcgUmVwbyBNaXhlciBUb29scyAtLT4KPHN2ZyBmaWxsPSIjN0Y3RjdGIiBoZWlnaHQ9IjI1NnB4IiB3aWR0aD0iMjU2cHgiIHZlcnNpb249IjEuMSIgaWQ9IkNhcGFfMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmlld0JveD0iMCAwIDMyLjA1NSAzMi4wNTUiIHhtbDpzcGFjZT0icHJlc2VydmUiPgoNPGcgaWQ9IlNWR1JlcG9fYmdDYXJyaWVyIiBzdHJva2Utd2lkdGg9IjAiLz4KDTxnIGlkPSJTVkdSZXBvX3RyYWNlckNhcnJpZXIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCIgc3Ryb2tlPSIjQ0NDQ0NDIiBzdHJva2Utd2lkdGg9IjEuNjY2ODYwMDAwMDAwMDAwMiI+IDxnPiA8cGF0aCBkPSJNMy45NjgsMTIuMDYxQzEuNzc1LDEyLjA2MSwwLDEzLjgzNSwwLDE2LjAyN2MwLDIuMTkyLDEuNzczLDMuOTY3LDMuOTY4LDMuOTY3YzIuMTg5LDAsMy45NjYtMS43NzIsMy45NjYtMy45NjcgQzcuOTM0LDEzLjgzNSw2LjE1NywxMi4wNjEsMy45NjgsMTIuMDYxeiBNMTYuMjMzLDEyLjA2MWMtMi4xODgsMC0zLjk2OCwxLjc3My0zLjk2OCwzLjk2NWMwLDIuMTkyLDEuNzc4LDMuOTY3LDMuOTY4LDMuOTY3IHMzLjk3LTEuNzcyLDMuOTctMy45NjdDMjAuMjAxLDEzLjgzNSwxOC40MjMsMTIuMDYxLDE2LjIzMywxMi4wNjF6IE0yOC4wOSwxMi4wNjFjLTIuMTkyLDAtMy45NjksMS43NzQtMy45NjksMy45NjcgYzAsMi4xOSwxLjc3NCwzLjk2NSwzLjk2OSwzLjk2NWMyLjE4OCwwLDMuOTY1LTEuNzcyLDMuOTY1LTMuOTY1UzMwLjI3OCwxMi4wNjEsMjguMDksMTIuMDYxeiIvPiA8L2c+IDwvZz4KDTxnIGlkPSJTVkdSZXBvX2ljb25DYXJyaWVyIj4gPGc+IDxwYXRoIGQ9Ik0zLjk2OCwxMi4wNjFDMS43NzUsMTIuMDYxLDAsMTMuODM1LDAsMTYuMDI3YzAsMi4xOTIsMS43NzMsMy45NjcsMy45NjgsMy45NjdjMi4xODksMCwzLjk2Ni0xLjc3MiwzLjk2Ni0zLjk2NyBDNy45MzQsMTMuODM1LDYuMTU3LDEyLjA2MSwzLjk2OCwxMi4wNjF6IE0xNi4yMzMsMTIuMDYxYy0yLjE4OCwwLTMuOTY4LDEuNzczLTMuOTY4LDMuOTY1YzAsMi4xOTIsMS43NzgsMy45NjcsMy45NjgsMy45NjcgczMuOTctMS43NzIsMy45Ny0zLjk2N0MyMC4yMDEsMTMuODM1LDE4LjQyMywxMi4wNjEsMTYuMjMzLDEyLjA2MXogTTI4LjA5LDEyLjA2MWMtMi4xOTIsMC0zLjk2OSwxLjc3NC0zLjk2OSwzLjk2NyBjMCwyLjE5LDEuNzc0LDMuOTY1LDMuOTY5LDMuOTY1YzIuMTg4LDAsMy45NjUtMS43NzIsMy45NjUtMy45NjVTMzAuMjc4LDEyLjA2MSwyOC4wOSwxMi4wNjF6Ii8+IDwvZz4gPC9nPgoNPC9zdmc+';
    public static $close = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20x%3D%220px%22%20y%3D%220px%22%0D%0Awidth%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%2030%2030%22%3E%0D%0A%20%20%20%20%3Cpath%20d%3D%22M%207%204%20C%206.744125%204%206.4879687%204.0974687%206.2929688%204.2929688%20L%204.2929688%206.2929688%20C%203.9019687%206.6839688%203.9019687%207.3170313%204.2929688%207.7070312%20L%2011.585938%2015%20L%204.2929688%2022.292969%20C%203.9019687%2022.683969%203.9019687%2023.317031%204.2929688%2023.707031%20L%206.2929688%2025.707031%20C%206.6839688%2026.098031%207.3170313%2026.098031%207.7070312%2025.707031%20L%2015%2018.414062%20L%2022.292969%2025.707031%20C%2022.682969%2026.098031%2023.317031%2026.098031%2023.707031%2025.707031%20L%2025.707031%2023.707031%20C%2026.098031%2023.316031%2026.098031%2022.682969%2025.707031%2022.292969%20L%2018.414062%2015%20L%2025.707031%207.7070312%20C%2026.098031%207.3170312%2026.098031%206.6829688%2025.707031%206.2929688%20L%2023.707031%204.2929688%20C%2023.316031%203.9019687%2022.682969%203.9019687%2022.292969%204.2929688%20L%2015%2011.585938%20L%207.7070312%204.2929688%20C%207.5115312%204.0974687%207.255875%204%207%204%20z%22%3E%3C%2Fpath%3E%0D%0A%3C%2Fsvg%3E';
    public static $junk = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADwAAAA8CAYAAAA6/NlyAAAACXBIWXMAAAsTAAALEwEAmpwYAAAFSElEQVR4nN1be4hXRRT+1q11y6xcqdAioywkqUjoHZllkGSvLcEwMKigDQtye0JCENgvegoFmRSUBfWHoFhZFNVqGVlRVihFadSuKVFo2WNLvXHkmzid5u6d+/r9ZvtgcPfcOWfOt3dmzjkzV6BedAC4H8AWAElGGwDQoM6wRSOAqG2iM2wxQBJnBvQ9W73pYYuEra7+TUdHjjVatkWxxhtNIBrVGh/IsUbLIoo1njR5zbV8jX/Xgin9bSsJz2gyaSF7ISLA53ToxBpsn0TbnyEirKJTF9VgeyZtv4KIsJhO9dRg+0bafgIR4W46tdDI3wDwsqf/uwDWeOTS93UjW0jbMkY0mEunlhr5dsq7AsJLF2Wio7GUchkjGkyjU31G/gnlUwIIT6FMdDRWU34uIsIxdGqTkS+nvDuAcDdloqOxmfKjERFGAtgD4E8A7Ur+KJ2dH0B4PmWi4zCCNvdwjKiwlQ6PV7JbKFsUQHgRZaLjcDhl3yNCrKNzpyvZ5ZStCCC8gjLRcTiDsvdjrmlnKdsnU7Y+gPB6ykTHYVYdtXSj4ny3Vzk8hrIdAYR3UCY6Dr111NJblOAbABNRDL5NJy0WW8JdKX8Y37rOg6MAfKXG27sXuF/W8t9+AJMKGHfTb1lALLaE02LwMs8yCYVw6Dfc9o7pfhjFtE5+3sYqJQ9Oo+4HAbHYEk6LwR9SfmpOX45XpzF9AEb7CIOxzu2WP5FEKMZTT8ITMmKxJZy2HLZRPi6HHzJbfqDeKgD72THt4B1qKv3CtDEEkiQMMknozIjFdkzfWu2krUHaDsFZas9YafxIJQxmS89Q/iuACwIH3ESdiRmx2I7pi8HHUvZ14NhTAfxMnRcA7GueD0nYkX6Kz/4AcFnAoH3sf15GLLZj+mLw+ZS9HXjU9Bv7PwdgH0+fJIuwoA3A43wuU+uKjIF9pZwvFtsxfTE4reS06KZvCX0Vn1GUMGjgAfb5C8AcpMMV6wuM/DWWeQ5rzO/vAHjV6CxIOVTQmEOfEvqYRjYXYYc72E82knkpfXrY50mUxxLauiHl+XUAdusMKgNJXsKC2xXpmz3Pp6tAXxbv0ZbYtLiJPiT0CXURBt+uG+xO/BudaqfsZTKTF6Jzq1r3IwvMtEoJC65Wa6fhWVdJRe2aFLK7AFyLfEjKEBZcpUg/ZDaMGdyIdhYguZO6+oZBbD+oNk4ZG80m7A7Jf6f+4hwZUR60qUxs0HM+1lTC4JtwQf/5lKBfFDb5uaSEraQqwoJz1Gb1oietK0r2WTXNfbt1ywgLTgHwI229ZBL3vNAFzPaKLtyTOglLewvAAQXs7M+sK4mZ8DSWku7tbjXFdyhGs1hwxzErC5SpLdm0jlMX5XJqMTbAzsHqKEbO2SZzHT8d06Y1c4iwNEEdoH0M4JAh7Izh2bM7SJSrm7SwlFWx1UZ4Nq9BRPexlEplnPo6YCNvEiwOA/Ap+3wB4AhPH5147Cp4k5jUlVpaHKoK/M3mUuxIAF/y2QZzTeODSy13NzO1nKeKh7sCdcaqE0iZsiewuZvBjwLXOViwNK14uE0Nlvdw/CDmyDZ3Xue5MM9Cj6qF7WFDZYTvUdPpehSDlH4PM2xJe6RgCQn64EiLb5USvrfkhlEXZld9xNOmDtIlJFyJ+HAxY7T70mdEUcLtplK5FPGi9DFtu6pU8hzEtxJTMyq2pOqrlhgwVMWW+AjrSiXvZVos0Jdpb6qK7T+ER/HLuaLXpTFBX5euBnCgj3DZC/HYkHoh7v4SZT95iBETzCcP/a36jxqtavcJYdmVhbR+0/+39s9nS38D9qTHIMcTU2cAAAAASUVORK5CYII=';
    public static $trash = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20x%3D%220px%22%20y%3D%220px%22%0D%0Awidth%3D%228%22%20height%3D%228%22%0D%0AviewBox%3D%220%200%2024%2024%22%3E%0D%0A%20%20%20%20%3Cpath%20d%3D%22M%2010%202%20L%209%203%20L%204%203%20L%204%205%20L%207%205%20L%2017%205%20L%2020%205%20L%2020%203%20L%2015%203%20L%2014%202%20L%2010%202%20z%20M%205%207%20L%205%2022%20L%2019%2022%20L%2019%207%20L%205%207%20z%22%3E%3C%2Fpath%3E%0D%0A%3C%2Fsvg%3E';
    public static $draft = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAABkCAYAAABw4pVUAAAACXBIWXMAAAsTAAALEwEAmpwYAAAEI0lEQVR4nO2dTW7UQBCFn2BWLIEowClga0dhjTThEPkB8ZNkLpQhCgqKlKwQh2CFiJAYOAFixx6SRpZsaWK5Pe6Z7qrq7npSLazOOG1/rmq/GscBVCqVSqXyJ6OBIeeATAoECiTGi4BM3AdqIgkFAn4ICgT8Jz4aIAWAMuNxcUCqyW5kPE4m19TNNcjEfaAmkmAH0q6puW0baUDaNTW3bSMNiAYUiBF8IYjLEG4fUKgP6V9TchsnE3cpMJGEAgE/BFFAuH1AoT7kJhBuH1CqD+EvC0ZwsJcsDcgGwu0DCvUhsnxAqT5Ey5apz8EXiSUr1/gK4J4EINw+oBDgQy4B3O84Z485gHD7gJJ5+xuAtY7zNQFwDeCQGkjO8QPAQwuM5mcqKAcKBMFhzAA8WABjHso+V4Zw+4CCYHzDAcY8lLccQLh9QBl4vBqDI4x5KG+ogeRYpsYO+6igvFYg8LKAP7KcyNsA3jtCWbl82XbO7QMKgm3bmtGGcuoI5VUIINw+oAy8bVszqjI16oBy4gjlpW8gKcd3S5lqFvDzDii3ALyjWFNMhr2ptR4YTZzWmdGG8tERyl4oIBJ8QulhvKtRuGX5zEkHlGeOF8AVgN0QQFL1GajL07nlc8d1ZjTadwTSQNnBQJmMfcZQKJ8APK/L2p8l51BB2cYA5Qpj3LFw90HxERWUzWWBpOwzJj13UyGhHLXKnxOQVH3GpHWcVFAGwegDklNv6twC5YIaBjJbwEcAzhyg3AHwixKGCxApPsL0jK/am+qC8pkShguQWH3G2LE3dVFnRqWnAP5SwnABEvPXrmcdjvt2bfq69ve7fhbrHzUMZPQd+KmH3lRwGH1AYvYZWwF7U0Fh9AGJ1WdQ9KaCwYDndM2pN2VCwIgRyExQb8o7DBcgkn3GRHJvKhQQqT5jIr03FQoIZ8xi7k2lBmQWe2/KFxDJPiOq3pSrbL9cqs8Yx9abcpURGJcA1lPpTblKIoy7KfWmXDV0YlQ+Yz213pSrhk4u5eemjBQY8Jzey8Ys5d6Uq6TCGAvqTbXnEVS2ielzUzcvAnYg+twUbmQkOxCuMmWE9Kba5ZFMUmBI6k11rVVkGjrxXJ6bGlluHMg0dPI5PDc16rmLI5MhiBiemxotuKUmEzcMI6A3NcTfkMk2AYq/zzACelMNjOT/f8iy7w45JuxNzWfGojWQTIb5FUeGqTfl2oYhk28Y15ZX4D2px0JcACYwjOh9iC1DDomhHDnAEL+GrOpDbFAOiKAcOWZG0mtIEz8t7xR5ERjKtANGdff2YYV9kskkBmUaAEZSQPqg7HmGEgpGckAWQbkSDiNJICGhTAPDSBZIH5TdJaFQwCAFIkm7jlC6YKg8a2cgFIUhCMpUM4Ne2xYoCkMQlKlmhhwoU4UhR5sKQ6VSqcCt/0kPcucfMlnUAAAAAElFTkSuQmCC';

    public static $arrow_drop_down = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0Ij48cGF0aCBmaWxsPSJub25lIiBkPSJNMCAwaDI0djI0SDBWMHoiLz48cGF0aCBkPSJNNyAxMGw1IDUgNS01SDd6Ii8+PC9zdmc+';
    public static $arrow_drop_up = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0Ij48cGF0aCBmaWxsPSJub25lIiBkPSJNMCAwaDI0djI0SDBWMHoiLz48cGF0aCBkPSJNNyAxNGw1LTUgNSA1SDd6Ii8+PC9zdmc+';
}

/**
 * Message list struct used for user notices and system debug
 */
trait Hm_List {

    /* message list */
    private static $msgs = array();

    /**
     * Add a message
     * @param string $string message to add
     * @return void
     */
    public static function add($string) {
        self::$msgs[] = self::str($string, false);
    }

    /**
     * Return all messages
     * @return array all messages
     */
    public static function get() {
        return self::$msgs;
    }

    /**
     * Flush all messages
     * @return null
     */
    public static function flush() {
        self::$msgs = array();
    }

    /**
     * Stringify a value
     * @param mixed $mixed value to stringify
     * @return string
     */
    public static function str($mixed, $return_type=true) {
        $type = gettype($mixed);
        if (in_array($type, array('array', 'object'), true)) {
            $str = print_r($mixed, true);
        }
        elseif ($return_type) {
            $str = sprintf("%s: %s", $type, $mixed);
        }
        else {
            $str = (string) $mixed;
        }
        return $str;
    }

    /**
     * Log all messages
     * @return bool
     */
    public static function show() {
        return Hm_Functions::error_log(print_r(self::$msgs, true));
    }
}

/**
 * Notices the user sees
 */
class Hm_Msgs { use Hm_List; }

/**
 * System debug notices
 */
class Hm_Debug {
    
    use Hm_List;

    /**
     * Add page execution stats to the Hm_Debug list
     * @return void
     */
    public static function load_page_stats() {
        self::add(sprintf("PHP version %s", phpversion()));
        self::add(sprintf("Zend version %s", zend_version()));
        self::add(sprintf("Peak Memory: %d", (memory_get_peak_usage(true)/1024)));
        self::add(sprintf("PID: %d", getmypid()));
        self::add(sprintf("Included files: %d", count(get_included_files())));
    }
}

/**
 * Easy to use error logging
 * @param mixed $mixed vaule to send to the log
 * @return boolean|null
 */
function elog($mixed) {
    if (DEBUG_MODE) {
        $bt = debug_backtrace();
        $caller = array_shift($bt);
        Hm_Debug::add(sprintf('ELOG called in %s at line %d', $caller['file'], $caller['line']));
        return Hm_Functions::error_log(Hm_Debug::str($mixed));
    }
}
