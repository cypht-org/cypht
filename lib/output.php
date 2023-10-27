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
    public static $three_dot = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2030%2030%22%20width%3D%2230px%22%20height%3D%2230px%22%3E%20%20%20%20%3Cpath%20d%3D%22M%207%204%20C%206.744125%204%206.4879687%204.0974687%206.2929688%204.2929688%20L%204.2929688%206.2929688%20C%203.9019687%206.6839688%203.9019687%207.3170313%204.2929688%207.7070312%20L%2011.585938%2015%20L%204.2929688%2022.292969%20C%203.9019687%2022.683969%203.9019687%2023.317031%204.2929688%2023.707031%20L%206.2929688%2025.707031%20C%206.6839688%2026.098031%207.3170313%2026.098031%207.7070312%2025.707031%20L%2015%2018.414062%20L%2022.292969%2025.707031%20C%2022.682969%2026.098031%2023.317031%2026.098031%2023.707031%2025.707031%20L%2025.707031%2023.707031%20C%2026.098031%2023.316031%2026.098031%2022.682969%2025.707031%2022.292969%20L%2018.414062%2015%20L%2025.707031%207.7070312%20C%2026.098031%207.3170312%2026.098031%206.6829688%2025.707031%206.2929688%20L%2023.707031%204.2929688%20C%2023.316031%203.9019687%2022.682969%203.9019687%2022.292969%204.2929688%20L%2015%2011.585938%20L%207.7070312%204.2929688%20C%207.5115312%204.0974687%207.255875%204%207%204%20z%22%2F%3E%3C%2Fsvg%3E';
    public static $close = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20x%3D%220px%22%20y%3D%220px%22%0D%0Awidth%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%2030%2030%22%3E%0D%0A%20%20%20%20%3Cpath%20d%3D%22M%207%204%20C%206.744125%204%206.4879687%204.0974687%206.2929688%204.2929688%20L%204.2929688%206.2929688%20C%203.9019687%206.6839688%203.9019687%207.3170313%204.2929688%207.7070312%20L%2011.585938%2015%20L%204.2929688%2022.292969%20C%203.9019687%2022.683969%203.9019687%2023.317031%204.2929688%2023.707031%20L%206.2929688%2025.707031%20C%206.6839688%2026.098031%207.3170313%2026.098031%207.7070312%2025.707031%20L%2015%2018.414062%20L%2022.292969%2025.707031%20C%2022.682969%2026.098031%2023.317031%2026.098031%2023.707031%2025.707031%20L%2025.707031%2023.707031%20C%2026.098031%2023.316031%2026.098031%2022.682969%2025.707031%2022.292969%20L%2018.414062%2015%20L%2025.707031%207.7070312%20C%2026.098031%207.3170312%2026.098031%206.6829688%2025.707031%206.2929688%20L%2023.707031%204.2929688%20C%2023.316031%203.9019687%2022.682969%203.9019687%2022.292969%204.2929688%20L%2015%2011.585938%20L%207.7070312%204.2929688%20C%207.5115312%204.0974687%207.255875%204%207%204%20z%22%3E%3C%2Fpath%3E%0D%0A%3C%2Fsvg%3E';
    public static $junk = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20xmlns%3Axlink%3D%22http%3A%2F%2Fwww.w3.org%2F1999%2Fxlink%22%20width%3D%2223%22%20height%3D%2223%22%20viewBox%3D%220%200%2023%2023%22%3E%0A%20%20%3Cpath%20d%3D%22M1%204L1%205.5820312L11%2012L21%205.5820312L21%204L1%204%20z%20M%201%207.7519531L1%2010.552734L1%2020L12.421875%2020L14%2020L14.757812%2020L15.757812%2019L13.636719%2016.878906L17.878906%2012.636719L20%2014.757812L21%2013.757812L21%2013L21%209.1953125L21%207.7519531L11%2014L1%207.7519531%20z%20M%2017.878906%2015.464844L16.464844%2016.878906L18.585938%2019L16.464844%2021.121094L17.878906%2022.535156L20%2020.414062L22.121094%2022.535156L23.535156%2021.121094L21.414062%2019L23.535156%2016.878906L22.121094%2015.464844L20%2017.585938L17.878906%2015.464844%20z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $trash = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20xmlns%3Axlink%3D%22http%3A%2F%2Fwww.w3.org%2F1999%2Fxlink%22%20viewBox%3D%220%200%2028%2028%22%3E%0A%20%20%3Cpath%20d%3D%22M6%208v16c0%201.1.9%202%202%202h14c1.1%200%202-.9%202-2V8H6zM24%204h-6c0-.6-.4-1-1-1h-4c-.6%200-1%20.4-1%201H6C5.4%204%205%204.4%205%205s.4%201%201%201h18c.6%200%201-.4%201-1S24.6%204%2024%204z%22%20%2F%3E%0A%3C%2Fsvg%3E';
    public static $draft = 'data:image/svg+xml,%3C%3Fxml%20version%3D%221.0%22%20encoding%3D%22iso-8859-1%22%3F%3E%0A%3C%21--%20Uploaded%20to%3A%20SVG%20Repo%2C%20www.svgrepo.com%2C%20Generator%3A%20SVG%20Repo%20Mixer%20Tools%20--%3E%0A%3C%21DOCTYPE%20svg%20PUBLIC%20%22-%2F%2FW3C%2F%2FDTD%20SVG%201.1%2F%2FEN%22%20%22http%3A%2F%2Fwww.w3.org%2FGraphics%2FSVG%2F1.1%2FDTD%2Fsvg11.dtd%22%3E%0A%3Csvg%20fill%3D%22%23000000%22%20version%3D%221.1%22%20id%3D%22Capa_1%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20xmlns%3Axlink%3D%22http%3A%2F%2Fwww.w3.org%2F1999%2Fxlink%22%20%0A%09%20width%3D%22800px%22%20height%3D%22800px%22%20viewBox%3D%220%200%2031.854%2031.854%22%0A%09%20xml%3Aspace%3D%22preserve%22%3E%0A%3Cg%3E%0A%09%3Cpath%20d%3D%22M15.94%2C11.735c0%2C0.588-0.476%2C1.064-1.065%2C1.064H5.298c-0.588%2C0-1.065-0.476-1.065-1.064c0-0.587%2C0.477-1.063%2C1.065-1.063%0A%09%09h9.577C15.465%2C10.672%2C15.94%2C11.148%2C15.94%2C11.735z%20M21.52%2C11.087l5.342%2C4.791L15.535%2C28.505l-5.34-4.791L21.52%2C11.087z%0A%09%09%20M20.008%2C16.188l-0.033-0.029c-0.127-0.114-0.293-0.172-0.461-0.163c-0.168%2C0.009-0.328%2C0.085-0.439%2C0.211l-6.079%2C6.776%0A%09%09c-0.253%2C0.281-0.229%2C0.713%2C0.052%2C0.965c0.126%2C0.115%2C0.291%2C0.174%2C0.461%2C0.164c0.168-0.01%2C0.327-0.086%2C0.44-0.213l6.108-6.81%0A%09%09C20.293%2C16.828%2C20.27%2C16.423%2C20.008%2C16.188z%20M21.27%2C13.751l-0.301%2C0.336c-0.111%2C0.126-0.17%2C0.292-0.162%2C0.461%0A%09%09c0.01%2C0.169%2C0.086%2C0.328%2C0.211%2C0.441l0.035%2C0.031c0.127%2C0.113%2C0.293%2C0.171%2C0.461%2C0.162c0.17-0.009%2C0.328-0.085%2C0.44-0.211%0A%09%09l0.728-0.811l-0.51-0.457C21.91%2C13.467%2C21.506%2C13.489%2C21.27%2C13.751z%20M22.035%2C10.51l5.341%2C4.791l1.312-1.462l-5.34-4.791%0A%09%09L22.035%2C10.51z%20M31.047%2C11.21l-1.877%2C2.093l-5.341-4.79l1.88-2.093c0.247-0.28%2C0.6-0.448%2C0.975-0.468%0A%09%09c0.375-0.021%2C0.742%2C0.109%2C1.021%2C0.359l3.234%2C2.903C31.52%2C9.735%2C31.569%2C10.629%2C31.047%2C11.21z%20M29.996%2C10.264l-3.233-2.902%0A%09%09l-0.937%2C1.042l3.233%2C2.903L29.996%2C10.264z%20M9.557%2C24.428l5.339%2C4.791l-6.31%2C2.586c-0.243%2C0.098-0.521%2C0.051-0.716-0.125%0A%09%09c-0.196-0.177-0.273-0.447-0.201-0.699L9.557%2C24.428z%20M10.045%2C26.13l-0.722%2C2.507l1.411%2C1.268l2.412-0.986L10.045%2C26.13z%0A%09%09%20M8.215%2C24.285l-0.623%2C2.162H2.859c-1.332%2C0-2.413-1.08-2.413-2.412V6.493c0-1.332%2C1.08-2.413%2C2.413-2.413h2.94V3.906%0A%09%09c0-0.492%2C0.399-0.89%2C0.89-0.89h0.74C7.414%2C2.909%2C7.406%2C2.781%2C7.406%2C2.661C7.406%2C1.194%2C8.599%2C0%2C10.067%2C0%0A%09%09c1.469%2C0%2C2.661%2C1.194%2C2.661%2C2.661c0%2C0.12-0.01%2C0.248-0.023%2C0.354h0.749c0.492%2C0%2C0.89%2C0.399%2C0.89%2C0.89v0.174h2.87%0A%09%09c1.332%2C0%2C2.412%2C1.081%2C2.412%2C2.413v4.63l-2.128%2C2.372V7.506c0-0.699-0.565-1.265-1.264-1.265h-1.891v1.201%0A%09%09c0%2C0.491-0.399%2C0.89-0.89%2C0.89H6.69c-0.492%2C0-0.89-0.399-0.89-0.89V6.241H3.874c-0.699%2C0-1.265%2C0.567-1.265%2C1.265V23.02%0A%09%09c0%2C0.701%2C0.567%2C1.266%2C1.265%2C1.266H8.215z%20M9.003%2C2.661c0%2C0.124%2C0.023%2C0.248%2C0.061%2C0.355h2.005c0.04-0.107%2C0.062-0.23%2C0.062-0.355%0A%09%09c0-0.587-0.477-1.065-1.064-1.065C9.479%2C1.596%2C9.003%2C2.074%2C9.003%2C2.661z%20M14.949%2C16.341l0.991-1.105%0A%09%09c-0.014-0.576-0.484-1.054-1.064-1.054H5.298c-0.588%2C0-1.065%2C0.493-1.065%2C1.082c0%2C0.587%2C0.477%2C1.082%2C1.065%2C1.082h9.577%0A%09%09C14.9%2C16.344%2C14.925%2C16.342%2C14.949%2C16.341z%20M4.233%2C18.791c0%2C0.588%2C0.477%2C1.062%2C1.065%2C1.062H11.8l1.907-2.127H5.298%0A%09%09C4.71%2C17.727%2C4.233%2C18.203%2C4.233%2C18.791z%22%2F%3E%0A%3C%2Fg%3E%0A%3C%2Fsvg%3E';

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
