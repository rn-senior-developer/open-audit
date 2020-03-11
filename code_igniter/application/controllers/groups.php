<?php
/**
#  Copyright 2003-2015 Opmantek Limited (www.opmantek.com)
#
#  ALL CODE MODIFICATIONS MUST BE SENT TO CODE@OPMANTEK.COM
#
#  This file is part of Open-AudIT.
#
#  Open-AudIT is free software: you can redistribute it and/or modify
#  it under the terms of the GNU Affero General Public License as published
#  by the Free Software Foundation, either version 3 of the License, or
#  (at your option) any later version.
#
#  Open-AudIT is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU Affero General Public License for more details.
#
#  You should have received a copy of the GNU Affero General Public License
#  along with Open-AudIT (most likely in a file named LICENSE).
#  If not, see <http://www.gnu.org/licenses/>
#
#  For further information on Open-AudIT or for a license other than AGPL please see
#  www.opmantek.com or email contact@opmantek.com
#
# *****************************************************************************
*
* PHP version 5.3.3
* 
* @category  Controller
* @package   Groups
* @author    Mark Unwin <marku@opmantek.com>
* @copyright 2014 Opmantek
* @license   http://www.gnu.org/licenses/agpl-3.0.html aGPL v3
* @version   GIT: Open-AudIT_3.3.0
* @link      http://www.open-audit.org
*/

/**
* Base Object Groups
*
* @access   public
* @category Controller
* @package  Groups
* @author   Mark Unwin <marku@opmantek.com>
* @license  http://www.gnu.org/licenses/agpl-3.0.html aGPL v3
* @link     http://www.open-audit.org
 */
class Groups extends MY_Controller
{
    /**
    * Constructor
    *
    * @access    public
    */
    public function __construct()
    {
        parent::__construct();
        $this->load->model('m_groups');
        // This endpoint allows all users.orgs children and the users.org_id parents to be permitted
        $this->load->model('m_orgs');
        $this->user->org_list = implode(',', $this->m_orgs->get_user_all($this->user->id));
        unset($this->user->org_parents);
        inputRead();
        $this->output->url = $this->config->config['oa_web_index'];
    }

    /**
    * Index that is unused
    *
    * @access public
    * @return NULL
    */
    public function index()
    {
    }

    /**
    * Our remap function to override the inbuilt controller->method functionality
    *
    * @access public
    * @return NULL
    */
    public function _remap()
    {
        $this->{$this->response->meta->action}();
    }


    /**
    * Process the supplied data and create a new object
    *
    * @access public
    * @return NULL
    */
    public function create()
    {
        if (stripos($this->response->meta->received_data->attributes->sql, 'where @filter') === false or 
            stripos($this->response->meta->received_data->attributes->sql, 'where @filter or') !== false) {
            // We don't have the HIGHLY RECOMMENDED @filter in our SQL
            // Ensure the user creating this query has the admin role
            $allowed = false;
            if (in_array("admin", $this->user->roles)) {
                $allowed = true;
            }
            if ($allowed === false) {
                unset($allowed);
                log_error('ERR-0022', 'groups::create');
                redirect('/groups');
            }
            unset($allowed);
        }
        include 'include_create.php';
    }

    /**
    * Read a single object
    *
    * @access public
    * @return NULL
    */
    public function read()
    {
        include 'include_read.php';
    }

    /**
    * Process the supplied data and update an existing object
    *
    * @access public
    * @return NULL
    */
    public function update()
    {
        if (!empty($this->response->meta->received_data->attributes->sql) and
            (stripos($this->response->meta->received_data->attributes->sql, 'where @filter') === false or 
            stripos($this->response->meta->received_data->attributes->sql, 'where @filter or') !== false)) {
            // We don't have the HIGHLY RECOMMENDED @filter in our SQL
            // Ensure the user creating this query has the admin role
            $allowed = false;
            if (in_array("admin", $this->user->roles)) {
                $allowed = true;
            }
            if ($allowed === false) {
                unset($allowed);
                log_error('ERR-0022', 'groups::create');
                redirect('/groups');
            }
            unset($allowed);
        }
        include 'include_update.php';
    }

    /**
    * Delete an existing object
    *
    * @access public
    * @return NULL
    */
    public function delete()
    {
        include 'include_delete.php';
    }

    /**
    * Collection of objects
    *
    * @access public
    * @return NULL
    */
    public function collection()
    {
        include 'include_collection.php';
    }

    /**
    * Supply a HTML form for the user to create an object
    *
    * @access public
    * @return NULL
    */
    public function create_form()
    {
        include 'include_create_form.php';
    }

    /**
    * Supply a HTML form for the user to update an object
    *
    * @access public
    * @return NULL
    */
    public function update_form()
    {
        include 'include_update_form.php';
    }

    /**
    * Return the result of running a query
    *
    * @access public
    * @return NULL
    */
    public function execute()
    {
        if (empty($this->response->meta->properties) OR $this->response->meta->properties === '*') {
            $this->response->meta->properties = $this->config->config['devices_default_group_columns'];
        }
        $group = $this->m_groups->read($this->response->meta->id);
        $this->response->meta->sub_resource_name = @$group[0]->attributes->name;
        $this->response->data = $this->m_groups->execute($this->response->meta->id, $this->response->meta->properties);
        $this->response->meta->filtered = count($this->response->data);
        $this->response->meta->total = count($this->response->data);
        output();
    }

    /**
    * Supply a HTML form for the user to upload a collection of objects in CSV
    *
    * @access public
    * @return NULL
    */
    public function import_form()
    {
        $this->load->model('m_database');
        $this->response->data = $this->m_database->read('groups');
        include 'include_import_form.php';
    }

    /**
    * Process the supplied data and create a new object
    *
    * @access public
    * @return NULL
    */
    public function import()
    {
        include 'include_import.php';
    }

    /**
    * The requested table will have optimize run upon it and it's autoincrement reset to 1

    *
    * @access public
    * @return NULL
    */
    public function reset()
    {
        include 'include_reset.php';
    }

}
// End of file groups.php
// Location: ./controllers/groups.php
