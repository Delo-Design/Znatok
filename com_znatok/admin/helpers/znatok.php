<?php
/*
 * @package     Znatok Package
 * @subpackage  com_znatok
 * @version     __DEPLOY_VERSION__
 * @author      Delo Design - delo-design.ru
 * @copyright   Copyright (c) 2021 Delo Design. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://delo-design.ru/
 */

defined('_JEXEC') or die;

use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;

class ZnatokHelper extends ContentHelper
{
	/**
	 * Configure the linkbar.
	 *
	 * @param   string  $vName  The name of the active view.
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public static function addSubmenu($vName)
	{
		JHtmlSidebar::addEntry(Text::_('COM_ZNATOK_DASHBOARD'),
			'index.php?option=com_znatok&view=dashboard',
			$vName == 'dashboard');
	}
}