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
     * @return void
     */
    abstract protected function output_content($content);

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
            $this->output_content($response);
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
        foreach ($headers as $header) {
            Hm_Functions::header($header);
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

    public static $power = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAOlJREFUOI2VkjFuwkAQRV+sNKZEKJh7UCEBl4BwhEhpQpSSFu5CgyjgIECiSIhLBBpoAhT+lker9dqMNPLsnz9f650PxXFTBiMqIzwq8BzghnoAvAI/QF1n+wsN4BcYFg0PgasG3jwCY9X/wMAdbgJHEd4N7j7iROcjkFiBqRpzR9i3hZWwmQV3AjsVBPrCvi14ERhTHrG4lwywayw1DfDkciPgoLpdQSDj7K3AWvVHBYFPfdcWbAEnXSsk8kW+xqbbHJEbaQF0SR+sBvSApXpXAm4cmZv48g+PC91ISE2yBc7KDanZXnwDd6ZsRKAfKovZAAAAAElFTkSuQmCC';
    public static $home = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAHFJREFUOI3FkMsNgCAQBQfjCUvRlkyMlWlR2IMFcMYLJARxV+OBSebA520ewDMW2KJWuFdlBBwQoi7uvWIBfBZOemCVgqlyGSzdgUGrrHkAk1ZZ8wRmk7UI0vsqGIDuY+hG+wG9cGaKdfWP2j/h94D2XMGCMeGhOf42AAAAAElFTkSuQmCC';
    public static $box = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAADtJREFUOI1jZGBg+M9AAWCiRPPgAf9xYHTwAZs6isOAEYdtDAwMDF/R+NykGkAUoNgLowYMFgMoSQf/AFJDEffBNhqvAAAAAElFTkSuQmCC';
    public static $env_closed = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUBHFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=';
    public static $star = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wQOFS87gxllewAAAF9JREFUOMtjZGBg+M8AAYxQGsZHB4zYBJkYKAQsJKjF5rImmMR/NIXE4GZ0DaQY0MaARQOxBnRSGohvsAUOqV6owGcALgvQcTWp0YgOWhgYGJiJMYARnyTFKXHUAAYGABeMO2KUzKckAAAAAElFTkSuQmCC';
    public static $globe = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAASNJREFUOI1907FKQ0EQBdBjldhaa28TgmAldnb+gP/gF+gHKAiCYCIGFdv0lhYiYmtriEUsFKysTRASi901j3WfAwPv7d57d2fuLH9jDT28YBJziHO0C/jfWMQVZv/kNIo3S+SHCugU62jEveNM6D4XyU8+zA64icJVzHm15rT4GcmlWvu4xo7Ql2nC9SL5FQfF7rCAES5jaf3IOSN0e4bbGnISeIq4d4zj90C8zgzfWKkR2Fd2ZVwVWMVWgbwUxWsFhvFnO141jwY+agSeCXakhQmOCiLL2MMFHiv4DsGK3OPdml7AhvnAtdJisjLlqIbcwlvEdKsbTWE8k8AXNoUxbgren5jbdyf0Ri7SK5STP6ZuiVyNtjBhg3jiWOh2p1pzih9Ox36Q1K2kawAAAABJRU5ErkJggg==';
    public static $doc = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFpJREFUOI3dkEEKACAIBLfo4f28ToJWm0adGghCdFwEgEaeUOHgCZoniQi2kqiAStgAWzBJTgVGkoZmTSJ1Q4407dAJZCNLtJq9T1DUP7rZ8DSBd/Vlwg9u0AEYijfoDYVTdgAAAABJRU5ErkJggg==';
    public static $monitor = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAGBJREFUOI1jYGBguMTAwPCfTHyCEcogG7AgsRlJ1PufgYGBgYkS20cNGCwGUJyQmBgYGE5RoP8YvtSH7jKsaqkaiEEMDAyvGRA5DZuL/jMwMLxiYGDwx2YYsmZC+CVMEwCAuidyZ/rWAAAAAABJRU5ErkJggg==';
    public static $cog = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAONJREFUOI2Vk00OAUEQhT+zkiC4gz2CcRpxAJO4gMRJLHAIq7Hxk7iQzGywmG5502qESiqpqX71+lVNNVRbDDydD7/g3tYC+vK9E4KN5AdAwyq+OvAZWAGZENxdzmNSoK4EYwH/4g+gF6q4/UFw9EWREBwkzoEE6AJtF+dynurNMbB3AH/DIpTnSPx5RjHkmAqJHYOgbWEjAwjFkEKrWcAImEoL3mYGdi5xri14W1PuMXGyO8CS8oxWlpoLv//Gc1g8+qPYu659aZVPfK5y5nIXwTRDFd8e01byA6vYMn3OkyrQC5Q1dBrXQiO8AAAAAElFTkSuQmCC';
    public static $people = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAN1JREFUOI2d0E0uBVEQxfHfk4dBD5lZgYFNmAgDQqyCeAkLYBfW8WIHxEfELsx8znzkGTCpRl/VyY2T3Nx0nar/6brUaQ9PeMSocuZby/gszmZf836k3GE3ag1uC8B5NrydJG2Ed1DUXzPAZQI4DW+xBvCeAF7CmynqZxngLQG0SbM9q3V0kQDapAU8497P4/7RVgJY7+mdxhHmSmOEh0jaSQYbrOEmAsYwj0MMK5KOiz+cwEl8XGElUhqs4vp3EpaSNX0kxfJMAjAsvUFLqdAg7k5/tnctCEz9A9DRFyWUZi0vm1MaAAAAAElFTkSuQmCC';
    public static $caret = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAACRJREFUGJVjYECABgYC4D8hRf8JKfqPTRETIXtJtgKnJAMuSQC7gAx6LfypjQAAAABJRU5ErkJggg==';
    public static $folder = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAADBJREFUOI1jZGBg+M+AHTDiEEcBTMQowgcY8biAKECxCwYeDHwYjBowbAw4SYH+YwB6YwSnsuTkoAAAAABJRU5ErkJggg==';
    public static $chevron = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAADtJREFUGJVjYCACKDAwMByA0ljFDjAwMPxnYGB4ABVQgLL/Q+VQBB6gseGmIivCkERXhFUSWRFOSawAAEl7E3uv1iMcAAAAAElFTkSuQmCC';
    public static $check = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFFJREFUGJWdzDsOQEAABNCXiE8vkj2CTuPuJApOgVs4As1KxKcx5bzJ8DMtJlTJBw6okUKDESHihh09chF3rDcszsuAJcIDr6MZ3RueKZHdywPRLxDHyg6J8AAAAABJRU5ErkJggg==';
    public static $refresh = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAXtJREFUSIm11b9KHFEUBvBf7BK0VDD4r5E0glhIQDsLJQ/gJiAm+AQSKzsR8SFMG0RLQVA0JPgKgiZPoIVrZbp1/VPMLHN3dnZmFnY/ODDn3nu+795zz9xDPiawiXPcoIZ6/H2KDQznxG+3mxjDAZ7wUmA17GEwxbETz7fgC/7nED63Ga9iKebYDcab8D2D7AgVjKIvtnGs4iK1vo7j1FjTzsOJS8xkHTGFT7jNOTGinIdpOcO7EuQNvMd9nsBBauedkJNcaKbAhKRanpVLS1nyF6I6bzhHHZJviS42z5wHApUOBUrhJhAY7YVALRDo6zZ51wmzBKqBP9ILgevAn++FwO/AX+m2ANHDVZf8aLO9ENmXVNI1+rstMIKHQOQPBjqIH8C3okUVzc3kH+ZKkH/E3zimbYtsYF1rx/qFNXwQvbJvMYmvOEmtfcR0kciy5nSVtSoWS5wY0Z38lFRXUdP/obXpgzcFQmP4jAVMYSiOucOV6CU+FLXMTLwC6Xmw7eTc8o8AAAAASUVORK5CYII=';
    public static $big_cog = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAXVJREFUSImtlsEyA0EQhj/hAXgAHORIOEqJgyqiXFURJVUeAHcOXkCpxGu4OgjXRPACDryD2ji62HWYmarJVPfuRPJX9WF7/v57Zrt7ZyEeHSCzdj9CXBSWgNRL8AssxgSWBF8TWAl8Z8BUEHcacFaBo6KEV94ue8AhUAUGnt/ZF7BuRfue/1wTPxFE/mMpsOMf1eG96HiRyIBPbfFtAifo+ILTQYIfYF9J3gfugBf7vKDwLoAP7QSbwo4SYFvg1pGLX5WEy8CtEiCJO+wK/AHQxswOYN5ZKhAz4DlH3OFViU2tdm7BriMS3ORpSJM8UZSAR5tNwkaERk3xD7VsGVMYqcj1HPE9gZ8ALbwi+9gSAgaYbpHEvwX+UJvOBEFzgtAs8ISZcjdkNcyHTsK85YroCjsa1Xqa+NoExJ1VnKjfpuElMw6WtQX/wukCB+gXTmLXGpiJd/7LouxNYQdtIUEr4FSA4yJxDdKlL/b5OPB/Wx5ig/4AeR3UqLNaCmAAAAAASUVORK5CYII=';
    public static $big_caret = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAEtJREFUOI2lzkEKACAMA8Hgy/15PAnSRjCmx8JOC/SZYmcNU4QpQoQIESIVsBEFWMgNkMhwXks/aNcd4DlWgBVXwI5P4CvewHcMN15ViDhSMdkjzgAAAABJRU5ErkJggg==';
    public static $big_caret_left = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAElJREFUOI2ljkEKADAIw2Qv9+fbaSB1E2tzFBNqxuHkf5K3Ko8CUaYDKFOBl9wO/ORWoJJTYHUnsVQr5Ii8hAYjI2JkzI1IOB4Oxd84UoiIlQMAAAAASUVORK5CYII=';
    public static $search = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAYZJREFUSImtlLtOAkEUhj/UzpAYgom1uI1Kgmh8AyUx8VLZ+EYmYm8jwiuIsbCwokAsBY2FT2AMYAHCqsXOhrOT3WV2wkkmO5P/nP+by85AdBSBMtACuoCrvi3gQulW4QD3wJ9BuwPWkpifAH1Dc7/1gWNT87EoHANV4ABYARaADLAPVICRlnsUZ+5oM38DNqdMKA+0RU0PyEUlyz1/VTM1iQzQEbX1sKQiwaVuGJr7kSe4XQU9oSzESkJzP6rC41wXW0IsWQJKwqOpi10hZi0By8LjSxddJfwCKUvAvAC4UpgDvlU/BaQtAUui39MB72K8YwnYFv2ODngU4zNLwKnoP+jiFpP9+wHWE5o7wJDJOTphSXUBecH8JqcJ/uYfRJxjDu9w/MQ209+iHPAkavzWiIIcEnxNR8AN3iXK4p1XBtgDroBBiLkRpBdTmKRFQlaBW0OTzyl6LQzgRwHv4WriXX9Xre4ZuAR21QwbMYBhHMA04iCDWQDiINezAviQGt62DJT54j8ekcXOOaAJlgAAAABJRU5ErkJggg==';
    public static $spreadsheet = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFlJREFUOI3t0DEKgEAMRNG3auONvKF6ZgPaLCKChVGw8UMIKSYzDExYsN6cwFiquJMjSv0Gpe7zfcUKTdJ555ggxeMEx/L+Dr7sINAm9dGix5BIE5iTxi+yAfhoHdCQ1VFIAAAAAElFTkSuQmCC';
    public static $info = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAKtJREFUOI2l0k0OAUEQBeAvIsSSC1g5gI3gDiJxMpKJcAMXcCc/i2Frw0KJCYaZ8ZJKv3TVe13dXfzGDEccMC1Q/4YjrhH712StgME1hxfGNE7eYVLFoDSaSJDijDkaZQwSz0d7xLyMQRqiAYbB07zi+oe9dob3Y72U6SCLbXSwqCIehfiCbhWDTRisq4jhFAa9b0XfRvmR62Dsx298wsqf89DC0v0qqZyJvAEWoivRGHfiuQAAAABJRU5ErkJggg==';
    public static $bug = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAPpJREFUOI2Vkr9rwlAQxz/RKHXwDyid3Nu5f4K7QwYFESw4OXUt9M8qlNbVTg4qGCh0c7ODQ6HQahx6Lz4v90I9OMjLfX9dXqBYEfAIfAIb4MHAlNY9kKkenyOwNgTeywiR91wF9obAV4hcARbSiZCfDdyTYLtACkz94dRzWkpcnSAFVt75VadIgLlB1D0DOmrtvEb/EBhYxI8A+Bq4DczeHDkGfg3RnXyP2HKU+UlFIuQcnGisnL81scLf3fcDSSznnvDySinuGErgX3fTJbiUF3cc/7jMcN8CQzG8Aupu0OB4rx3gB3jxiBMRbntrX5TteaMANaAVAh8AR6Jp1Y4VfDEAAAAASUVORK5CYII=';
    public static $code = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3VjjESwCAIBHf8mD5dX6aNRQYhelbJlRzsAn9JATpQbwF1AsqNwXYu7M1gu0Wm2F2oYl/AyTl6LmWgBd9pb+46O4igYWeHkv3IoGRr+FYGqUEz6slPFcwAAAAASUVORK5CYII=';
    public static $person = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAMBJREFUOI2l0kFqAkEQheFPF1GzCXqMGLyB5BbZeAsXXkYSsoggZBeIeAcZdBWv4QSi7pLFtBDGsW30wdsUr/6upopqdfCK7+AXtE9kj9TEEr8lZ2ikAEYVzQcPUwCrCCBLAewjgF05XE8hxlQFWEfyXymASQTwdm4iilVljv+/wE0KgOJonpFjgzHuYg09zEPDUyQ3UFzmDN1D8SEU/487RR8t3OIR76VMjnv4dHrv5/xRwza8dIl+aoF0sa6+xD/4h0vlxe5JGQAAAABJRU5ErkJggg==';
    public static $rss = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAASdJREFUOI2V000rRGEYxvHfTJpZUPbY4kPITqyYUqTYoGTHpxBl6SOwkp1PMCt5KzbKLBDlrWy8ZGYzFu7pPKZpGnedOv/rPPd1nus+58nJqoY3VHCEQ5RR12HVW1wVLCPfiUEBfRjDBm4SoxMMdbqTRuUxi9swecdEu4Ya7nCABRRD78ZumNTamTTnf4gdNGo72UnLOAUMYgVnidGObIh7oR3rYLBL+ExMoCdi1rHY3PCNU6zL8o8kJjOhzQVfI5capPkv0R/6cmj3ETMv+zKjqUE3JnEVDy+iIYfz0OZj7WbwVqvsvYnJWmirwfvB48FlmMYrXlCKBVOyPxCGg2+CB4IfRXMj+3Ms6An+Ci4GV5u5y9/T1rj/8HfC1XZcijc/+R3iv+oHjPVkYOSl2fQAAAAASUVORK5CYII=';
    public static $rss_alt = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAPxJREFUOI2d0b0uRFEUhuFnpjAo9BKVn4JGxC2IC0AE94PET6iFRE0ltK5AI9QmRkk1MhkxJhnF7JOcrJzDZHaymnd/+81aa1fQRROveMQ97vBpwNMrqDYuMDesIKsfHGJ0WEFWz5gpE4xgEivYxUuJ5B2Lg4xUxQbqJZLp+KCj/wPX2EkdwTguCyRPwk5i4A3rufu9gszBX4KsTlFJmdhJB7P/CXo4yY0Td3KeCWpYwhG+CiRrKbcZeBsTwllAIwQb+outFnSxBS3cpseZJHayne72Az/L76CJ+RQ8DsGrxFcDf4hLvEnB5cDriU8F/hEFrRQcC/w78Vrg3V/3bJgjGmgApwAAAABJRU5ErkJggg==';
    public static $caret_left = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAElJREFUOI2ljkEKADAIw2Qv9+fbaSB1E2tzFBNqxuHkf5K3Ko8CUaYDKFOBl9wO/ORWoJJTYHUnsVQr5Ii8hAYjI2JkzI1IOB4Oxd84UoiIlQMAAAAASUVORK5CYII=';
    public static $caret_right = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAEtJREFUOI2lzkEKACAMA8Hgy/15PAnSRjCmx8JOC/SZYmcNU4QpQoQIESIVsBEFWMgNkMhwXks/aNcd4DlWgBVXwI5P4CvewHcMN15ViDhSMdkjzgAAAABJRU5ErkJggg==';
    public static $calendar = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAD1JREFUOI1jZGBg+M9AHmBkYGBgYCJTMxwMvAEDDxgZBlMsMMJMJZKPYQBZYGiFAUEDyAJUCQNyDPgHYwAAG1wGINjZwEkAAAAASUVORK5CYII=';
    public static $circle_check = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAOpJREFUOI2N0k1OwlAUhuFHJtZ9IKIJaVyCm9EFiYGBazHGRWDAoSK6A5y0DnpKbsqlcJIv6c95v3t+LvtxizlW+AstMUOZyd/FBZ5R96gK8yIHvx2BU712TY6d3GqN73iepT2fAn/hElfYRDvlAA99g4lY4w4fGMS3M9zTTLt70qbzPgzoGj/Jv3eaNaU9DjEOk88euMY2ZzAKYJzANxl4Z7C0P+nWpIV/M3CNBc06cusaHYFrPNJcz+qASR9cYdKWOe9JPKRp0qZCcz1PhV9wrhNFVJJrJy17moPTKPGkuSTb0CIGNukm/wMZsaY/CZVfmAAAAABJRU5ErkJggg==';
    public static $circle_x = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wQOFQwPHATmrwAAANlJREFUOMutk80NgkAUhD+8iI0IF0IswWa0IDFwsBZjLAIDd6AFvICXIXlZF/DgSybZ3cy8/4VvOwAFUANvoQJyIGXBdsANGBcwyHnoEz9XxBYP14mN3AiuqAFac89tzZa0FxrPewR0ppx0A5w9/aiBoyK2OtdAYDgBcJrIoycaJhuA2ESf8EJjGheczIlHoN/wB6sWosfCXBYlGsecuBPmnFzQeg7GQeQhdiYbO8ZkKqNwyL6Gue+Z7UOo9fx1le/A1m1mqEyGlc+U+cTWUuCqJemFUg1LXPIHHSqQvkbxU6kAAAAASUVORK5CYII=';
    public static $key = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIZAQcXtT02GAAAAI1JREFUOMvFkkEKgzAQRV96hqinKlJ6oe4EwaMIoSdI9UYtxaVuZhFCnUSz6IfZ/OG/T4ZAWndgBhaZCbiRqQFYd6bPaQ4DI2CBCnDitRpgjgA22NXivTTAEgGqYNeI99UAnwjgpLkBninAQzleOL4kvALXknB3NPyWN/uzzZQe7KeMsjQ5f/1Cof4P2AA4X2HYT0C3uwAAAABJRU5ErkJggg==';
    public static $save = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIcEScSeyu1dwAAAElJREFUOMtjZMAN/qPxGbEpYmKgEIwagGpAAzTk/2OJAQY0uQZcBqIbgg03EHJVAyWa8RnSQGr4NFCiGdmQBoZBDRhxxDn9UiIAeTEh9qw/B/IAAAAASUVORK5CYII=';
    public static $plus = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAC1JREFUOI1jZMAN/qPxGbEpYsJjAFFg1ABIyKKHNn1dMPAGYE1dUDCaEullAAAi0gMdELO4MAAAAABJRU5ErkJggg==';
    public static $minus = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAB9JREFUOI1jYBgFAw8YGRgY/lNiABOlLhh4A0bBYAAAlmIBB5xuwAAAAAAASUVORK5CYII=';
    public static $book = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAG9JREFUOI1jYGBg8GRgYHjKwMDwHwdGB/8ZGBhOIAvg04zLALg4Iw5FyIARiwFwcSYCmgkCYg1ogGIMwEKk5npcksSEAT699AuD4WwAAwMiaZqQ64JPUDYzuS6YwoA/MxHMZOwMDAyTGRgYPpBjAADNnzUOiHYz3QAAAABJRU5ErkJggg==';
    public static $undo = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAJBJREFUOI3NzrENwjAURdETqpSUiOxBFfZAYgFqJkhHhoFpyCZQpCI0QbIskxgaeNIrvq17/+dfU6FFh37sFSes5+A97hje9IbdFPyIgDNqlGO3uKQkVWLzceLSA1bhQ5vYPJdNOHSRoM4QLMOhjwRlhqAogmGIPzMEFmgS8EvY5EgEkrDZcEryMRxKvoZ/kyd/ZzJ7vvWJEAAAAABJRU5ErkJggg==';
    public static $paperclip = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAUJJREFUOI2F0z1LHFEUxvGfgWgwBNyUFjuSLAlYWwgWaVQsbYQ0gXyG1PkEadJkGyFIRCsbURHSpEgT8BtYuCGRIBISXPGtWBiLe8Yd1mH2gYFz7jn/M3Ofe4d6vcQurnCJbbwYwtxpBv+RoxdPjn9oDYNncRbAFzzGE2zE2lYdPIfzaFzDg1KtUfqKSr3CRTStYmSgPhm1v1XwvGRUjnYF/FAyNMfmILyI6yh+lEx6V6qPYifqp8jKcKa/5w8BH0f+BmPYi/wE04Nvb+u73cDvyL9hAvuR/5HuxT11oqGFtxF/x1N8jfwXnlfBJON6Eb8O4BA/Iu5gSjK5UkfRmOERDiIvBjUlY3O8rxrwSf/cSY4vYwXjJfgGS1UDmuhG02c8i/UW1ofBheb1j7L4eYq4i4U6uFAmHelP6VJ1YnvNOugWhUJgO4Hk69MAAAAASUVORK5CYII=';
    public static $tags = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAJNJREFUSIntkTEKhDAUBQcbvYZ32WZLr6JiZZHFM8uyC4s2Cp+P0ST+ZiEDaeQxowYyASye49SuAb5qM94JmEXOAiYROWyBEugtI3JUbs8qyy+Rg26TDweB5IjzyEzv5H8jhQq8dDWCX+jQEf/2T+DDxS8KjWj5A3jHyM8iWl4Dc4r8KKLlO1OqXEZ8chlJkmcy16zTV6ptbvRZbgAAAABJRU5ErkJggg==';
    public static $tag = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAH1JREFUOI2l0MEJwCAQRNEf7EEtx9KsKAhpwFSWXFyQ4Bo3EbysMw8V4FJ2ZnFpwDIi4R3wQACKBZGg72bRchMJhQkwRSRQWjECxwBQkayElz7WARXYgDR7Z7dSy1cB+IO4bvgJcY+hFTm1g4zxM63Ia3mGLJdHiLncI8PyDcqkVU5hsKL4AAAAAElFTkSuQmCC';
    public static $history = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFhJREFUOI1jZGBg+M+AHTBCabzyTDgkSQYWUJv+MzAwmGGRN0eSN8FlCEwBSfIUe4FiA1iwiOHzBn1cwIhFjIEBh8sGPhBhBlghiZljUYdTnpFhsOSFgQMAuB4PzYX/hzYAAAAASUVORK5CYII=';
    public static $sent = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAALhJREFUOI3F0CFuAlEUheGvGRBUNkGBg42gGywah4EFIFAVLKALaHUrmjQNO8A0aPaBIKTNhIeZSWAYYCYjOMlT5/5/3r0Uz7zEbG4CXvFQRRDwhqiKIOAT9UuDTxjhA2tsEGcEAQs0jsEIM2xzhi+9nxSu4bsEGLBDLxW8lIT/8ZzCzcQW8Id39NFKfpY9YozB8e6TpPhF9/ymJ4I9htniC0s8XoFTwTivWKF9A4Zp6aJoOlUF980Bg1BHxT1HkP8AAAAASUVORK5CYII=';
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
     * Show all messages
     * @param string $type can be one of "print", "log", or "return"
     * @return mixed
     */
    public static function show($type='print') {
        if ($type == 'log') {
            Hm_Functions::error_log(print_r(self::$msgs, true));
        }
        elseif ($type == 'return') {
            return self::$msgs;
        }
        else {
            print_r(self::$msgs);
        }
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
 * @return void
 */
function elog($mixed) {
    if (DEBUG_MODE) {
        $bt = debug_backtrace();
        $caller = array_shift($bt);
        Hm_Debug::add(sprintf('ELOG called in %s at line %d', $caller['file'], $caller['line']));
        return Hm_Functions::error_log(Hm_Debug::str($mixed));
    }
}
