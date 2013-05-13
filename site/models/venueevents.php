<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php

 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Venueevents Model
 *
 * @package JEM
 * @since 0.9
*/
class JEMModelVenueevents extends JModelLegacy
{
	/**
	 * Events data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * venue data array
	 *
	 * @var array
	 */
	var $_venue = null;

	/**
	 * Events total
	 *
	 * @var integer
	 */
	var $_total = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;

	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();

		$app =  JFactory::getApplication();

		$id = JRequest::getInt('id');
		$this->setId((int)$id);

		// Get the paramaters of the active menu item
		$params 	=  $app->getParams('com_jem');

		//get the number of events from database
		$limit       	= $app->getUserStateFromRequest('com_jem.venueevents.limit', 'limit', $params->def('display_num', 0), 'int');
		$limitstart 	= $app->getUserStateFromRequest('com_jem.venueevents.limitstart', 'limitstart', 0, 'int');

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

	}

	/**
	 * Method to set the venue id
	 *
	 * @access	public
	 * @param	int	venue ID number
	 */
	function setId($id)
	{
		// Set new venue ID and wipe data
		$this->_id			= $id;
		$this->_data		= null;
	}

	/**
	 * set limit
	 * @param int value
	 */
	function setLimit($value)
	{
		$this->setState('limit', (int) $value);
	}

	/**
	 * set limitstart
	 * @param int value
	 */
	function setLimitStart($value)
	{
		$this->setState('limitstart', (int) $value);
	}

	/**
	 * Method to get the events
	 *
	 * @access public
	 * @return array
	 */
	function &getData( )
	{
		$pop	= JRequest::getBool('pop');

		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$query = $this->_buildQuery();

			if ($pop) {
				$this->_data = $this->_getList( $query );
			} else {
				$this->_data = $this->_getList( $query, $this->getState('limitstart'), $this->getState('limit') );
			}
		}

		$k = 0;
		$count = count($this->_data);
		for($i = 0; $i < $count; $i++)
		{
			$item = $this->_data[$i];
			$item->categories = $this->getCategories($item->id);

			//remove events without categories (users have no access to them)
			if (empty($item->categories)) {
				unset($this->_data[$i]);
			}

			$k = 1 - $k;
		}

		return $this->_data;
	}

	/**
	 * Total nr of events
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the total nr if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}

	/**
	 * Method to get a pagination object for the events
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_pagination))
		{
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}

	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	function _buildQuery()
	{
		// Get the WHERE and ORDER BY clauses for the query
		$where		= $this->_buildWhere();
		$orderby	= $this->_buildOrderBy();

		//Get Events from Database
		$query = 'SELECT DISTINCT a.id, a.dates, a.enddates, a.times, a.endtimes, a.title, a.locid, a.datdescription, a.created, '
				. ' l.venue, l.city, l.state, l.url, l.street, c.catname, ct.name AS countryname, '
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
				. ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
				. ' FROM #__jem_events AS a'
				. ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
				. ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid '
				. ' LEFT JOIN #__jem_countries AS ct ON ct.iso2 = l.country '
				. $where
				. $orderby
				;

				return $query;
	}

	/**
	 * Build the order clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildOrderBy()
	{

		$app =  JFactory::getApplication();

		$filter_order		= $app->getUserStateFromRequest('com_jem.venueevents.filter_order', 'filter_order', 'a.dates', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.venueevents.filter_order_Dir', 'filter_order_Dir', '', 'word');

		$filter_order		= JFilterInput::getInstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getInstance()->clean($filter_order_Dir, 'word');

		if ($filter_order != '') {
			$orderby = ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir;
		} else {
			$orderby = ' ORDER BY a.dates, a.times ';
		}

		return $orderby;


	}

	/**
	 * Method to build the WHERE clause
	 *
	 * @access private
	 * @return array
	 */
	function _buildWhere( )
	{
		$app 			=  JFactory::getApplication();
		$task 			=	JRequest::getWord('task');
		$params 		=  $app->getParams();
		$jemsettings 	=  JEMHelper::config();

		$user		= JFactory::getUser();
		if (JFactory::getUser()->authorise('core.manage')) {
			$gid = (int) 3;      //viewlevel Special
		} else {
			if($user->get('id')) {
				$gid = (int) 2;    //viewlevel Registered
			} else {
				$gid = (int) 1;    //viewlevel Public
			}
		}

		$filter_state 		= $app->getUserStateFromRequest('com_jem.venueevents.filter_state', 'filter_state', '', 'word');
		$filter 			= $app->getUserStateFromRequest('com_jem.venueevents.filter', 'filter', '', 'int');
		$search 			= $app->getUserStateFromRequest('com_jem.venueevents.search', 'search', '', 'string');
		$search 			= $this->_db->escape(trim(JString::strtolower($search)));
		 
		 
		$where = array();
		 
		// First thing we need to do is to select only needed events
		if ($task == 'archive') {
			$where[] = ' a.published = -1 && a.locid = '.$this->_id;
		} else {
			$where[] = ' a.published = 1 && a.locid = '.$this->_id;
		}
		$where[] = ' c.published = 1';
		$where[] = ' c.access  <= '.$gid;
		 
		 
		/* get excluded categories
		 $excluded_cats = trim($params->get('excluded_cats', ''));
		 
		if ($excluded_cats != '') {
		$cats_excluded = explode(',', $excluded_cats);
		$where [] = '  (c.id!=' . implode(' AND c.id!=', $cats_excluded) . ')';
		}
		// === END Excluded categories add === //
		*/
		 
		 
		if ($jemsettings->filter)
		{
			 
			if ($search && $filter == 1) {
				$where[] = ' LOWER(a.title) LIKE \'%'.$search.'%\' ';
			}
			 
			if ($search && $filter == 2) {
				$where[] = ' LOWER(l.venue) LIKE \'%'.$search.'%\' ';
			}
			 
			if ($search && $filter == 3) {
				$where[] = ' LOWER(l.city) LIKE \'%'.$search.'%\' ';
			}
			 
			if ($search && $filter == 4) {
				$where[] = ' LOWER(c.catname) LIKE \'%'.$search.'%\' ';
			}
			 
			if ($search && $filter == 5) {
				$where[] = ' LOWER(l.state) LIKE \'%'.$search.'%\' ';
			}
			 
		} // end tag of jemsettings->filter decleration
		 
		$where 		= (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');
		 
		return $where;
		 
		 
		
	}

	/**
	 * Method to get the Venue
	 *
	 * @access public
	 * @return array
	 */
	function getVenue( )
	{
		$user		=  JFactory::getUser();

		if (JFactory::getUser()->authorise('core.manage')) {
			$gid = (int) 3;      //viewlevel Special
		} else {
			if($user->get('id')) {
				$gid = (int) 2;    //viewlevel Registered
			} else {
				$gid = (int) 1;    //viewlevel Public
			}
		}

		//Location holen
		$query = 'SELECT *,'
				.' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as slug'
				.' FROM #__jem_venues'
				.' WHERE id ='. $this->_id;

		$this->_db->setQuery( $query );

		$_venue = $this->_db->loadObject();
		$_venue->attachments = JEMAttachment::getAttachments('venue'.$_venue->id, $gid);

		return $_venue;
	}

	function getCategories($id)
	{
		$user		=  JFactory::getUser();
		if (JFactory::getUser()->authorise('core.manage')) {
			$gid = (int) 3;      //viewlevel Special
		} else {
			if($user->get('id')) {
				$gid = (int) 2;    //viewlevel Registered
			} else {
				$gid = (int) 1;    //viewlevel Public
			}
		}

		$query = 'SELECT DISTINCT c.id, c.catname, c.access, c.checked_out AS cchecked_out,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
				. ' FROM #__jem_categories AS c'
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int)$id
				. ' AND c.published = 1'
				. ' AND c.access  <= '.$gid;
		;

		$this->_db->setQuery( $query );

		$this->_cats = $this->_db->loadObjectList();

		$k = 0;
		$count = count($this->_cats);
		for($i = 0; $i < $count; $i++)
		{
			$item = $this->_cats[$i];
			$cats = new JEMCategories($item->id);
			$item->parentcats = $cats->getParentlist();

			$k = 1 - $k;
		}

		return $this->_cats;
	}
}
?>