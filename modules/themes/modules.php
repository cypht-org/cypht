<?php

/**
 * Theme modules
 * @package modules
 * @subpackage themes
 */

if (!defined('DEBUG_MODE')) { die(); }


/**
 * Setup currently selected theme
 * @subpackage themes/handler
 */
class Hm_Handler_load_theme  extends Hm_Handler_Module {
    public function process() {
        $theme = $this->user_config->get('theme_setting', 'default');
        if ($theme == 'hn') {
            $this->user_config->set('list_style', 'news_style');
        }
        if ($theme == 'dark') {
            hm_theme_white_icons();
        }
        $this->out('theme', $theme);
    }
}

/**
 * Process theme setting from the general section of the settings page
 * @subpackage themes/handler
 */
class Hm_Handler_process_theme_setting extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('save_settings', 'theme_setting'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            $new_settings['theme_setting'] = $form['theme_setting'];
        }
        else {
            $settings['theme'] = $this->user_config->get('theme_setting', 'default');
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
    }
}

/**
 * Include theme css
 * @subpackage themes/output
 */
class Hm_Output_theme_css extends Hm_Output_Module {
    /**
     * Add HTML head tag for theme css
     */
    protected function output() {
        if ($this->get('theme') && in_array($this->get('theme'), array_keys(hm_themes($this)), true) && $this->get('theme') != 'default') {
            return '<link href="modules/themes/assets/'.$this->html_safe($this->get('theme')).'.css" media="all" rel="stylesheet" type="text/css" />';
        }
    }
}

/**
 * Theme setting
 * @subpackage themes/output
 */
class Hm_Output_theme_setting extends Hm_Output_Module {
    /**
     * Theme setting
     */
    protected function output() {

        $current = $this->get('theme', '');
        $res = '<tr class="general_setting"><td><label for="language_setting">'.
            $this->trans('Theme').'</label></td>'.
            '<td><select id="theme_setting" name="theme_setting">';
        foreach (hm_themes($this) as $name => $label) {
            $res .= '<option ';
            if ($name == $current) {
                $res .= 'selected="selected" ';
            }
            $res .= 'value="'.$this->html_safe($name).'">'.$label.'</option>';
        }
        $res .= '</select>';
        return $res;
    }
}

/**
 * Define available themes
 * @subpackage themes/functions
 */
function hm_themes($output_mod) {
    return array(
        'default' => $output_mod->trans('White Bread (Default)'),
        'blue' => $output_mod->trans('Boring Blues'),
        'dark' => $output_mod->trans('Dark But Not Too Dark'),
        'gray' => $output_mod->trans('More Gray Than White Bread'),
        'green' => $output_mod->trans('Poison Mist'),
        'tan' => $output_mod->trans('A Bunch Of Browns'),
        'terminal' => $output_mod->trans('VT100'),
        'lightblue' => $output_mod->trans('Light Blue'),
        'hn' => $output_mod->trans('Hacker News'),
    );
}

/**
 * White UI icons
 * @subpackage themes/functions
 */
function hm_theme_white_icons() {
    $icons = array(
        'calendar' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQETMNklvQbwAAADtJREFUOMtj/P///38GMgAjIyMjAwMDAxMDhWDgDRh4wDh4YoERCojlUy0WhlAY0CwlUiUMyDHgH4wBABYaEj454YBgAAAAAElFTkSuQmCC',
        'env_closed' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQETYYgvHAwQAAAFRJREFUOMvNkjEOgDAMA4++rPy8fdkxwAASwlI7lBuj2HGiwGo21RmDMpugADvQB7T90p6oVW1mmlrvwkfhw+i1j9SQBhAjhmSkXdNt/vEHaw3WcwAbyPslkKxh0AAAAABJRU5ErkJggg==',
        'box' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQETkQC7JUPAAAAD9JREFUOMtj/P///38GCgATw5AHjLjCgJGRkRGZ/////w8MDAz8VA8DRjyx8BWNz02qAfSJxlEDBosBlKSDfwDIjxcGBtR7UQAAAABJRU5ErkJggg==',
        'home' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQEToO2pA6nAAAAGhJREFUOMvd0MEJgDAMheEX8VRH0ZUEcTIdqu7gAD3/XgQFkbR4KPhBjgkvT3oBBGA5J6gE0AORSwT63OUJSDwlYFZGZM8KdF5kzwYMXmTPDox2S0FJ0WZmktToo/oHWu9Hr6MfdFDfAT+d6y1VFmwhAAAAAElFTkSuQmCC',
        'search' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQETsZQFiOGgAAAQFJREFUOMuVkk1KA0EUhCuBgPEEiQfwAi4EiTmB6wmusgq4SRA9gxK8iBKIWehFxIALzzD4tzCr/rKpgabRSU/BY5p61dX9ekqKABwAc2ANbFyvwC3QVx2Ac+CH//ENFHWbg4UL4ATYcw2ApXsBGOmPa1cnT2tueG3NF9CLG3M37rUDwMram5hcmzzOMBha+xKTG5OdDIN9a38rrq1mCCnRlvTu9VGGQaV5iw2evL7KMJj5+xjP1XdIAC5r5r+IQjVLm0UUpCVwCnRdA+AhSWUAJqnJyCHJRQDGrcSkJ2kq6UzSoaSWH/lZUinpzlyFz0b/EJhEowKUagpgDHwAJVBsATE1gdzGTVAZAAAAAElFTkSuQmCC',
        'star' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wQOFTImH3NlvgAAAGZJREFUOMvtkkEOgCAMBKfqo028qTHxr/5hvUiiSCOIR+dGaCd0qUkSgJkZQDjHhPuYhkq63ELnZSM6OBdmMl0aCgULcUOBYK0NcbuF82KE3hWk0ncYir4xwSypfRR4G/jZJv4C2AHdVvG0L8dP8QAAAABJRU5ErkJggg==',
        'doc' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQEgEhT7VWpAAAAFdJREFUOMvdkEEOgDAMwxLEw/fzcAFUJNIVygmfN9cKZMCOpIGMgkCppCjwkgeCe4n7kBwYXcFFwvg4QpIosKDJWXBcdEWusF2wxrHeCL4rmK3uCn+wwQZhHehD1gJkTQAAAABJRU5ErkJggg==',
        'power' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQEgMq6lHtrgAAAOhJREFUOMulkj0OQVEQhceLhlLEzz5UEmzCzxIkGkSpZS8aUbAQP5XYBBoaPs2R3Fz3/SRO8vLuOzNz3p05YxYDBEtBZH8i8v6aT7hRPlEA6JvZHigFistmdgR6ceo94K22h/4MgIk+X0DXL64CNyWM4oYIzEXdgJorsFBgleYCsBW9dMmjyGYGgY7ok0s+RRbSLAMKyn2GbCSD7Tk/NzKzi86NDALfnLMrsNN5nEFgqvfO7asO3NXbOKH/mWNj1Q8OnEVaAy0NrAi0gY1i76RtHDg3CeH6s4UBkRqwBA7AQ89ey1YJ1XwApyFJo3twLfIAAAAASUVORK5CYII=',
        'globe' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQEgQv1XqP5gAAAS5JREFUOMuFk7FKA2EQhOfS5GztbaxswiFoI3Y+hO/gG+QBDAiCoAkJRGzTCzaCQQRLW4MWp6ClluIFIZ/Nnqy//38OHBy3O7M7u3tSAGAdGAGPwNyeB2AIFEoBWAJOacbCxPMY+cYlHQMbQNtih4HQ9S+RSOVeUODchD2G3nONd6AX8wpMgDNg1+ayAAqZJ4AnYD8xnwwogbFZmxhnIJs2wGXDgDPgzvJegcreZ7J2AL6AlYRAN7GVygusATsR8rKJRwVakp4td1XSNNLAh6S3hLuy5UgXkirgwGdkWTaXtCmpK2ks6daFrwQUkR3vNQx0yx1cp/44CgTKBLkDvFhO3wdyO88an8C2nXFuuz9y65sC7VA9t04W//xM/T/kQKgABsDMKlbAPXDy49nhG8faR5/cSs2AAAAAAElFTkSuQmCC',
        'folder' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQEgU5OLUL9gAAADJJREFUOMtj/P///38GLICRkZGRgQjAxEAhYMTlAmIBxS4YeDDwYTBqwLAx4CQF+o8BAM+hDaETngtcAAAAAElFTkSuQmCC',
        'rss' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQEgY6ipEJjwAAAThJREFUOMuV071KXFEUxfF9hqCFgfTRNvoQkk5iNRkQlIA2URC75CkkgZR5hFiFdD7BVOIXaBPIFJOgEMdAGpMQp/nZ7JHDMJPo6v7rnL24e917S6TQj4ifEdGJiL2I2I2IdilF3EVGq4MNNO4SMIHHWMA2ulXQAWbjPkIDK/iaIVdY/NdAH9/wCWuYTH8KHzKkPzZkxP7nWKnO31VPMjuugyfYxFEV9H5QInbS2/9vsVjH70FIeg9zTXg5PPAXh3hd7T9fhSyn9yL5C8q4Dk4xnf5Geme5ZqN6M0/rgCk08TkPT3Kg4Di91bz7JvntqN0fVSGv0ttK/pj8LLkdWMIPXKKVF54PvsDkueRu8kzy98jhgXpV2/AneTL5epgfRET9t4mIKKX8iojbhksp12MZLfRwgWbcUzdCOwzg8bUaUgAAAABJRU5ErkJggg==',
        'rss_alt' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQEgcwc1/R0AAAARxJREFUOMudkr1KQ0EQhc/cIldF7AWx8KcwjYhvICLYGhHJ+2jhD/oACrZqJdr6BDaiYGdILLWKaJT489nshXXYC5dMefbstzNn1oBvSV1JbUm3kq4lXZnZq6oU6eoBx8DsoICivoBdYGhQQFH3wHQZoAaMA8vAFvBYAnkG5quMlAHrQKsEMuUv9IE2cA40gVrQR4CTBOTuXyYJwxPQiM63E56dKiEeAhY8vpM+MFNlCwfROD6TowKQAwvAHvCRgKwF30bis435QOtAxxk7YdVZoovNDHgDLoG6mT1IWpX0GXEnJTXM7FfSqdv6UpxBF5gLney7l86CvuL0Gx/iRTAuOr0V9AmnvxhA1NK7mY0Cw5J6kd43sxzI3Xg/fwwYfkl2/96LAAAAAElFTkSuQmCC',
        'monitor' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQEggs4MaRUAAAAF9JREFUOMvtkrsNgDAQQ23EOsyCxNBIULIFZIhHk3SRAhwFBa+6xh+dbGCTNOgZiwEUoC+Hbd8RluBOQX6DLxiEh9RJWgP62a2ltZb63hOBCUhkao0yBzDWKieusxfdCfJ1ZGpn+i3mAAAAAElFTkSuQmCC',
        'cog' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQEgkmGQhJDwAAAPJJREFUOMuVkzFuAjEQRb+3igTRhjvQQ5SwOU2UA2SlXICIk1AEDpEKqgWUKyBxB6qIbXhpjDIaeQX+1Xj859v+45E6AFT841HXANwDI7NeGIG5yY+BXqp4G8kNMAVORuA35i6cFXBnBZ7JwxkY+lv8ZAisL3WF0fg2cSupljSQVMa4Nfsr7/YSaM0J7wmTa7N/iiZXASDRlEEI4egESklHTyw6unpO5EKKWEh6kbR0b3xNcN+cRwtJlb3izL2xBkrgAfhwHk1TP3GT0cbGFz+Rj5E1cS9pF+NG0qfzpI25reEccobpyw1T/5bptOM86eL9AUFixWc3XELRAAAAAElFTkSuQmCC',
        'people' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQEgoiNUje1QAAAPFJREFUOMud000uREEUBeAqaT3pITMrMLAJE2FAiFUQnbAAVmBqHR070PETsQszvzMievAZqCe63E6/diaVd8+955z6eSm1AA7wgmf00yzAqr/YntR8WFwesF9qPdxXApfR8G7gtFW4o6r+HglcBwIXhVtuI/ARCLwVrlvVh83c3G+N4FhytTY4jRJcBQmGhVvCKx6bw40EdgKBzQm98zjBQk308VSc9oLBHjZwVwwGCYs4RmeaE86qhJ8J5+XjBmvFpYd13P44fQus1HvMGKWUOlNe9Cjn3C0pR2PXBG3+iZxzLinG+jtpRjRC0UP6F74AYlmfX1NlJ58AAAAASUVORK5CYII=',
        'person' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQEgsuJeWjvwAAAL9JREFUOMut0jFKQ0EUheE7FsaXRuIyVNyBuIs07sLCzYhioSDYBQzuQR5JpdswgpLusxkhPF+GMeaHaS7n/gwzJ6IHHOAWH/ncYBQ1YA8zv2kxqBFcWs9FjWBeELTdfOoRLCNi3VWXKaVmdbAT/6RP8FbIv9YI7guCu5pHHOQv6/KC3doujHCNBd5xhf3Swgme88K4kDvPzZzi6Gd4nIerPOAUDYY4w2Mns8Bh4MnmTBK+IqLZsAafCbZdpD/xDQ7nSgrVp0uHAAAAAElFTkSuQmCC',
        'info' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQEgwyfqVpNwAAAKZJREFUOMu10k0OAUEQxfEZEWLJBawcwEZwB5E4GYkIN3ABd/KxGLY2P5uSCIaZEW9V6ar3z+vuSpIvwgwnHDFNyirMdx2e+7UijJy6cIIpDthjkvxdaGKJDBfM0SgDWHrVvAwgC9MAw6izvPn680Gapu0HWD/Ka9X32EWCRRXzKMxXdKsAtgHYVI1/DkDv01ytQK+D8bffeJdg/es+tLCKq2R5G3kDWUncIwuA8U4AAAAASUVORK5CYII=',
        'bug' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQEg0kk2rtJwAAARlJREFUOMuVkzFLA0EQhd9cTomIP0Cs7LX2J6S3SKEggoKVla3gzwqIpk2qFObAg4CdnRYWEeQSP5tdGZNdNdMt8+bNm3mz0kIABtwAr8ALcK1VArhiOS5XIXhOEExyeAPMzAjFLUmNJFvAvZvZZoqgkDQGxkBX0qek2wSuBxTAEVADAy954KRWwCQxQg08uve9JyiALvDA3zECDgFLLe/iHwSnqcKnDHgPOMjkhrG+lDRLLG1uZhVQZtybf9sYry/Y13IEZSBoXOGHmbV/2Bi8P8koWeoMHAOF30GdmHEWcmVmBxWwFQ9pW1Il6VzSNPImur9JOpNUS9qRtB4VbERfg8cNcOcU9oEp0HF30/7tM+17ALAG7ObwXxBErsUZoqtGAAAAAElFTkSuQmCC',
        'code' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQEg4lz0COcgAAAFZJREFUOMvVkjEOACAIA4sf06fjy3DQSYla46BdSe4IFPgiZpasRk8B2gCJNvQzFzYz9LNBxthdKGP3wGF2eQARQBYRpV9EbbY81Eq49aobnbjTuudSAJWIFxhNJ9xxAAAAAElFTkSuQmCC',
        'refresh' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIQEhAT1LskNAAAAOhJREFUOMul00tKQ2EMBeBQKnbgOgSpVXBScCniLlyAoEgHIviWLqa4DHUginuQWq39nOTC9ae1cD0QSEhy8iIRBbCNOzxhgk884gobRexR3ehgiJnfqNvfOMMKTqCefJ9B7xigh3bKDm7xlTHPFWNFMEz7BeuxANjFuN5eNfMsKy9MToLjYjyRC4PBkuRDTEuJ3Db0ognyVNBukt+Kf6IVEa+pd5sSjFLfb7qDrTzjGN2mJDe5yDdsLil2MM+xilGSfOACfayl9HGePtibR9LJTspnqmOK0z9Pnk90jYesOEn9snzniIgfZ+Kj5XvXk6YAAAAASUVORK5CYII=',
        'chevron' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wITFjIwxNIUVAAAAD5JREFUGNOVjcEJADAIAzNK9p/CUdzk+rHQilCa15ELKr0CGAjAY1cAkCVcDBBqRTb2eXKLW7a/Oco2sn6yAG+SeRX0vYSzAAAAAElFTkSuQmCC',
        'circle_check' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIXARYef+fanwAAAP5JREFUOMuVk01uwjAUhMdRpYYLsOiaBohURT0Cl4EDQQULrgJUvUB3IOiy5e8GsOFj86iMSUL4JEtxkhk/j5+lAOAdGAEr4GhjCQyBTEUANWBMOSczj/PEX1Tn88qkwsoX1sDGnof+nqvwB7wCTWBr28kiST3dZy2p45z7kRTZOyepK0s7XGkbzBtWbRvYed8WsmPy99gAWmbyWyIGOOQZJCZoeeI0R/xvsMxJOvFOKAX2BcHOI0nTILAXSTMgAVJJM0n1gnAnztrz21L12Uh6KhEjKbuUOeJxBn4nxtaeVZkCz+F9iK2S053LNLgRB0YZ8AEsgIONOdAH3sL/z413lqgszXfqAAAAAElFTkSuQmCC',
        'key' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIZAQgSQs/eWAAAAJNJREFUOMvFkssJwzAQRHddgz9VhRDSUG4BgytQDYaU4MQdBYwvhufLHoRAshwd8kAHzTCzrJDIAcAdmIHVzge4SQ7AQJw+Z7LPCNRAA7xMu6YK5qCg9rzWtHeqYA0KGs/rTFuqxBZbcHc2uRMRd7T/gzymkjDApST8PBv+Agsw/TS5+MFieY2Zqqo5372SQv5fsAP546KRN4Vv2gAAAABJRU5ErkJggg==',
        'save' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wIcESgkMwk8IQAAAFJJREFUOMvtkrsKwEAIBN18+f75pEkhQe/ygJDiphNkUFdFA0CuJanq2+IlS5AEgElUqSTcRWfmeDjSROJLezUS3zrOSeJHFz4kjl+jKvNPP3EH61WZgXMMlJoAAAAASUVORK5CYII=',
        'book' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wQOFC4W3h9ieAAAAHRJREFUOMvdksEJgDAMRRMVXMglnMruoEO5iHdxgeelSKitLRU8+E/hN3mknwgwAhsJSSBvr9ZIDj8ALl9jTVaqqiHA+o28VBEAcICLvXUlwyIy+fr+xVwGuWy+yeDnADFXO9RucPi6rd1goUIW0AMzsNcATp/U4mn8NERgAAAAAElFTkSuQmC',
        'undo' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wQOFDAM9zyk3QAAAJhJREFUOMvV0rENAjEMheEL1ZWUCPaggj2QWICaCdJxw8A03CZQXMVHc4gowBGo4EkubOv9thJX1U8KMzRo0fVxxA7Td+Y1zl7rhNWQ+ZIZ9lig7mOJwwOkXzufvB3YdINJWmjyyQVvNU+TNgMsCgDjNOkyQF0ACCFJpM0Q7r0hjRBz8w2IWHo48cl/x0+vL35tziCx+itdAVZFDLL5ueHqAAAAAElFTkSuQmCC',
        'circle_x' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3wQOFQ0h2cnaIQAAAOZJREFUOMulk01uwkAMhV+yabhIkk0V9QhcBg4UKlj0LAhxCFCzD7kCbPi6cSrXzQQknvSkGct+47+RAoAPYAd0wM34DWyBRikAC+CLedxNvJgKPvI8Dn9Ewsu9MaIHLu6+9TV7p9LYT9grYHDlNLmkdexHlmWdpKWki3Fptsy7SVrJuh1TLS270p1r9/qIs2xMpERmggGuuV6FLUmqhBqoZ7I4yTYsFTwYUyKtgMZGMgpUE47DmE0Y4/tYxi44TzUs2je+D4Wt57PYA2+xmYVlcn/wmTb/goNQA3wCZ+BqPAHtb80OPyBztrpQou9vAAAAAElFTkSuQmCC',

    );
    foreach ($icons as $name => $value) {
        Hm_Image_Sources::$$name = $value;
    }
}

?>
