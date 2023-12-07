<?php
// This file is part of Fbcomments - https://github.com/ankitagarwal/moodle-block_fbcomments
//
// Fbcomments is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Fbcomments is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// For GNU General Public License, see <http://www.gnu.org/licenses/>.

/**
 * Fbcomments block class.
 *
 * @package   block_fbcomments
 * @copyright 2013 onwards Ankit Agarwal <ankit.agrr@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_fbcomments extends block_base {

    /** @const string order by constants. (used for comments ordering) */
    const ORDER_SOCIAL = 'social';

    /** @const string order by constants. (used for comments ordering) */
    const ORDER_TIME = 'time';

    /** @const string order by constants. (used for comments ordering) */
    const ORDER_REVERSE_TIME = 'reverse_time';

    /** @const int enable like button. */
    const BUTTON_ENABLE_LIKE = 1;

    /** @const int enable recommend button.*/
    const BUTTON_ENABLE_RECOMMEND = 2;

    /** @const int disable all buttons. */
    const BUTTON_DISABLE_ALL = 0;

    public function  __construct() {
        $this->title = get_string('pluginname', 'block_fbcomments');
    }

    public function has_config() {
        return false;
    }

    public function applicable_formats() {
        return array('all' => true);
    }

    public function specialization() {
        $this->title = isset($this->config->title) ?
                format_string($this->config->title) : format_string(get_string('newfbblock', 'block_fbcomments'));
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function get_content() {
        global $CFG;

        if (!empty($this->config->appid) && !empty($this->config->enablecomment)) {
            $meta = html_writer::tag("meta", "", array("property" => "fb:app_id", "content" => $this->config->appid));
            if (empty ($CFG->additionalhtmlhead)) {
                $CFG->additionalhtmlhead = '';
            }
            // Moodle creates a instance of block multiple times in every page, not sure why.
            if (strpos($CFG->additionalhtmlhead, $meta) === false) {
                $CFG->additionalhtmlhead .= $meta;
            }
        }

        if ($this->content !== null) {
            return $this->content;
        }
        $fblike = null;
        $fbcomment = null;
        $this->content = new stdClass();
        // Config wont be set if block is just added.
        if (!empty($this->config->urltype)) {
            switch ($this->config->urltype) {
                case 4 : $url = $this->get_mod_url();
                        break;
                case 3 : $url = $this->get_course_url();
                        break;
                case 2 : $url = new moodle_url($CFG->wwwroot);
                        break;
                case 1 :
                default : $url = $this->page->url;
                        break;
            }
        } else {
            // No point going any further.
            return '';
        }
        $url = $url->out(true);

        $this->content->text = '<div id="fb-root"></div>';
        $jscode = $this->get_fb_js();
        $this->page->requires->js_init_code($jscode);

        $color = $this->config->colorscheme;
        $attr = 'data-href="'.$url.'" colorscheme="'.$color.'"';
        $this->content->text .= $this->get_like_share_button($attr); // Add like/share button if needed.
        $this->content->text .= $this->get_comments_box($attr); // Add comments block.

        return $this->content;
    }

    /**
     * Return course url, which current page belongs to.
     *
     * @return moodle_url
     */
    public function get_course_url() {
        $context = $this->context->get_course_context();
        return $url = new moodle_url($context->get_url());
    }

    /**
     * Return mod url, which current page belongs to.
     *
     * @return moodle_url
     */
    public function get_mod_url() {
        // Context of the page holding the block.
        $context = $this->page->context;
        return $url = new moodle_url($context->get_url());
    }

    /**
     * The block should only be dockable when the title of the block is not empty
     * and when parent allows docking.
     *
     * @return bool
     */
    public function instance_can_be_docked() {
        return (!empty($this->config->title) && parent::instance_can_be_docked());
    }

    /**
     * Returns a list of allowed order-by parameters.
     *
     * @return array
     */
    public static function get_order_by_options() {
        $arr = array(
            self::ORDER_SOCIAL => get_string(self::ORDER_SOCIAL, 'block_fbcomments'),
            self::ORDER_TIME => get_string(self::ORDER_TIME, 'block_fbcomments'),
            self::ORDER_REVERSE_TIME => get_string(self::ORDER_REVERSE_TIME, 'block_fbcomments')
        );
        return $arr;
    }

    /**
     * Returns a list of allowed order-by parameters.
     *
     * @return array
     */
    public static function get_button_options() {
        $arr = array(
            self::BUTTON_DISABLE_ALL => get_string('disable'),
            self::BUTTON_ENABLE_LIKE => get_string('buttonlike', 'block_fbcomments'),
            self::BUTTON_ENABLE_RECOMMEND => get_string('buttonrecommend', 'block_fbcomments')
        );
        return $arr;
    }

    /**
     * Get Facebook js code.
     *
     * @return string
     */
    protected function get_fb_js() {
        return '(function(d, s, id) {
          var js, fjs = d.getElementsByTagName(s)[0];
          if (d.getElementById(id)) return;
          js = d.createElement(s); js.id = id;
          js.src = "//connect.facebook.net/en_US/all.js#xfbml=1";
          fjs.parentNode.insertBefore(js, fjs);
        }(document, "script", "facebook-jssdk"));';
    }

    /**
     * Get html for Facebook like and share button if enabled.
     *
     * @param string $attr Attributes to add to the like and share button.
     *
     * @return string HTML code for the like button.
     */
    protected function get_like_share_button($attr = '') {
        if (!empty($this->config->enablelike)) {
            $likeattr = 'data-send="false" data-show-faces="false"';

            if ($this->config->enablelike == 1) {
                // Like button.
                $likeattr .= ' data-action="like"';
            } else {
                // Recommend button.
                $likeattr .= ' data-action="recommend"';
            }

            // Add a share button if specified.
            if (!empty($this->config->enableshare)) {
                $likeattr .= ' data-share="true"';
            }
            return "<div class='fb-like' $likeattr $attr></div>";
        }
        return '';
    }

    /**
     * Get html for Facebook comments box if enabled.
     *
     * @param string $attr Attributes to add to the comments box.
     *
     * @return string HTML code for the comments box.
     */
    protected function get_comments_box($attr = '') {
        if (!empty($this->config->enablecomment)) {
            if (empty($this->config->numposts)) {
                $this->config->numposts = 10;
            }
            $commentattr = 'data-num-posts="'.$this->config->numposts.'"';
            if (empty($this->config->commentorder)) {
                $this->config->commentorder = self::ORDER_SOCIAL;
            }
            $commentattr .= 'data-order-by="'.$this->config->commentorder.'"';
            return "<div class='fb-comments' $commentattr $attr></div>";
        }
        return '';
    }
}
