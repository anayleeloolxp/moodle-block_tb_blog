<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Recent Blog Entries Block page.
 *
 * @package    block_tb_blog
 * @copyright  2020 Leeloo LXP (https://leeloolxp.com)
 * @author     Leeloo LXP <info@leeloolxp.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * This block simply outputs a list of links to recent blog entries, depending on
 * the context of the current page.
 */
class block_tb_blog extends block_base {

    /**
     * Initialize.
     *
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_tb_blog');
        $this->content_type = BLOCK_TYPE_TEXT;
    }

    /**
     * Return Applicable Formats.
     *
     * @return array
     */
    public function applicable_formats() {
        return array('all' => true, 'my' => false, 'tag' => false);
    }

    /**
     * Allow instance config.
     *
     * @return bool
     */
    public function instance_allow_config() {
        return true;
    }

    /**
     * Return Content of block.
     *
     * @return stdClass contents of block
     */
    public function get_content() {
        global $CFG;

        if ($this->content !== NULL) {
            return $this->content;
        }

        // verify blog is enabled
        if (empty($CFG->enableblogs)) {
            $this->content = new stdClass();
            $this->content->text = '';
            if ($this->page->user_is_editing()) {
                $this->content->text = get_string('blogdisable', 'blog');
            }
            return $this->content;
        } else if ($CFG->bloglevel < BLOG_GLOBAL_LEVEL and (!isloggedin() or isguestuser())) {
            $this->content = new stdClass();
            $this->content->text = '';
            return $this->content;
        }

        require_once($CFG->dirroot . '/blog/lib.php');
        require_once($CFG->dirroot . '/blog/locallib.php');
        require_once($CFG->libdir . '/filelib.php');

        $leeloolxplicense = get_config('block_tb_blog')->license;

        $url = 'https://leeloolxp.com/api_moodle.php/?action=page_info';
        $postdata = '&license_key=' . $leeloolxplicense;

        $curl = new curl;

        $options = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HEADER' => false,
            'CURLOPT_POST' => count($postdata),
        );

        if (!$output = $curl->post($url, $postdata, $options)) {
            $this->content->text = get_string('nolicense', 'block_tb_blog');
            return $this->content;
        }

        $infoleeloolxp = json_decode($output);

        if ($infoleeloolxp->status != 'false') {
            $leeloolxpurl = $infoleeloolxp->data->install_url;
        } else {
            $this->content->text = get_string('nolicense', 'block_tb_blog');
            return $this->content;
        }

        $url = $leeloolxpurl . '/admin/Theme_setup/get_recent_blog_entries';

        $postdata = '&license_key=' . $leeloolxplicense;

        $curl = new curl;

        $options = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HEADER' => false,
            'CURLOPT_POST' => count($postdata),
        );

        if (!$output = $curl->post($url, $postdata, $options)) {
            $this->content->text = get_string('nolicense', 'block_tb_blog');
            return $this->content;
        }

        $resposedata = json_decode($output);
        $settingleeloolxp = $resposedata->data->block_settings;

        if (empty($settingleeloolxp->interval_time_consider)) {
            $settingleeloolxp->interval_time_consider = 8400;
        }

        if (empty($settingleeloolxp->no_of_entires)) {
            $settingleeloolxp->no_of_entires = 4;
        }

        if (empty($settingleeloolxp->block_title)) {
            $settingleeloolxp->block_title = get_string('blocktitle', 'block_tb_blog');
        }
        $this->title = $settingleeloolxp->block_title;

        $this->content = new stdClass();
        $this->content->footer = '';
        $this->content->text = '';

        $context = $this->page->context;

        $url = new moodle_url('/blog/index.php');
        $filter = array();
        if ($context->contextlevel == CONTEXT_MODULE) {
            $filter['module'] = $context->instanceid;
            $a = new stdClass;
            $a->type = get_string('modulename', $this->page->cm->modname);
            get_string('viewallmodentries', 'blog', $a);
            $url->param('modid', $context->instanceid);
        } else if ($context->contextlevel == CONTEXT_COURSE) {
            $filter['course'] = $context->instanceid;
            $a = new stdClass;
            $a->type = get_string('course');
            get_string('viewblogentries', 'blog', $a);
            $url->param('courseid', $context->instanceid);
        } else {
            get_string('viewsiteentries', 'blog');
        }
        $filter['since'] = $settingleeloolxp->interval_time_consider;

        $bloglisting = new blog_listing($filter);
        $entries = $bloglisting->get_entries(0, $settingleeloolxp->no_of_entires, 4);

        if (!empty($entries)) {
            $entrieslist = array();
            $viewblogurl = new moodle_url('/blog/index.php');

            foreach ($entries as $entryid => $entry) {
                $fs = get_file_storage();
                $syscontext = context_system::instance();
                $files = $fs->get_area_files($syscontext->id, 'blog', 'attachment', $entryid);
                // Adding a blog_entry_attachment for each non-directory file.
                $attachments = array();
                foreach ($files as $file) {
                    if ($file->is_directory()) {
                        continue;
                    }
                    $attachments[] = new blog_entry_attachment($file, $entryid);
                }

                $viewblogurl->param('entryid', $entryid);
                $entrylink = html_writer::link($viewblogurl, $entry->subject, array('class' => 'recent_blogtitle'));

                if (isset($attachments[0]) && isset($attachments[0]) != '') {
                    $entrylink .= html_writer::img($attachments[0]->url, $entry->subject);
                }

                $entrylink .= html_writer::div(
                    substr_replace(
                        strip_tags($entry->summary), "...", 200
                    ) .
                    html_writer::link(
                        $viewblogurl,
                        'Read More',
                        array('class' => 'recent_blogmore')
                    ),
                    'recent_blogdescription'
                );

                $entrieslist[] = $entrylink;
            }

            $this->content->text .= html_writer::alist($entrieslist, array('class' => 'list'));
            $viewallentrieslink = html_writer::link($url, $strview);
            $this->content->text .= $viewallentrieslink;
        } else {
            $this->content->text .= get_string('norecentblogentries', 'block_tb_blog');
        }
    }

    /**
     * This plugin has no global config.
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }
}
