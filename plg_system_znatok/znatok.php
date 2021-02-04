<?php
/*
 * @package     Znatok Package
 * @subpackage  plg_system_znatok
 * @version     __DEPLOY_VERSION__
 * @author      Delo Design - delo-design.ru
 * @copyright   Copyright (c) 2021 Delo Design. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://delo-design.ru/
 */

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class plgSystemZnatok extends CMSPlugin
{
	/**
	 * Loads the application object.
	 *
	 * @var  CMSApplication
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected $app = null;

	/**
	 * Loads the database object.
	 *
	 * @var  JDatabaseDriver
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected $db = null;

	/**
	 * Affects constructor behavior.
	 *
	 * @var  boolean
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected $autoloadLanguage = true;

	/**
	 * Canonical link.
	 *
	 * @var  string
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected $canonical = null;

	/**
	 * Doubles protection.
	 *
	 * @throws  Exception
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function onAfterRoute()
	{
		// No doubles protection
		$this->fixDoubles();
	}

	/**
	 * Method to check page url, and redirect if link is not correct.
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected function fixDoubles()
	{
		if ($this->app->isClient('site'))
		{
			$option = $this->app->input->get('option');
			$view   = $this->app->input->get('view');
			$id     = $this->app->input->getInt('id');
			$catid  = $this->app->input->getInt('catid');
			$link   = null;

			if ($option == 'com_content')
			{
				JLoader::register('ContentHelperRoute', JPATH_SITE . '/components/com_content/helpers/route.php');

				// Content Category
				if ($view === 'category')
				{
					$link  = ContentHelperRoute::getCategoryRoute($id);
					$limit = $this->getContentCategoryLimit();
					if ($offset = $this->app->input->getInt('start'))
					{
						$page = $offset / $limit;
						if (is_float($page)) $link .= '&start=' . floor($page) * $limit;
					}
				}
				elseif ($view === 'categories')
				{
					// Content Categories
					$link = 'index.php?option=com_content&view=categories&id=' . $id;
				}
				elseif ($view == 'article')
				{
					// Content Article
					$link = ContentHelperRoute::getArticleRoute($id, $catid);
				}
			}

			if ($link)
			{
				$uri  = Uri::getInstance();
				$root = $uri->toString(array('scheme', 'host', 'port'));

				$link      = Route::_($link, false);
				$canonical = Uri::getInstance($link);

				// Add start variable
				if ($canonical->hasVar('start') && empty($canonical->getVar('start')))
				{
					$canonical->delVar('start');
				}
				if (!$canonical->getVar('start', false) && $uri->getVar('start', false))
				{
					$canonical->setVar('start', $uri->getVar('start'));
				}

				// Set canonical
				$this->canonical = $root . $canonical->toString(array('path', 'query', 'fragment'));

				// Add others variable
				foreach ($uri->getQuery(true) as $key => $value)
				{
					$value = trim($value);
					if (empty($value)) continue;

					// Add utm variables
					if (preg_match('#^utm_#', $key)) $canonical->setVar($key, $value);
				}

				// Redirect if need
				$current  = $uri->toString(array('path', 'query', 'fragment'));
				$redirect = $canonical->toString(array('path', 'query', 'fragment'));
				if (urldecode($current) != urldecode($redirect)) $this->app->redirect($redirect, 301);
			}
		}
	}

	/**
	 * Add paginationDescription.
	 *
	 * @param   string    $context  The context of the content being passed to the plugin.
	 * @param   object   &$row      The item object.
	 * @param   mixed    &$params   The view params.
	 * @param   integer   $page     The 'page' number.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onContentPrepare($context, &$row, &$params, $page = 0)
	{
		if ($this->params->get('pagination_description', 0))
		{
			$this->addPaginationDescription($context, $row, $params, $page);
		}
	}

	/**
	 * Add titles to paginationDescription.
	 *
	 * @param   string    $context  The context of the content being passed to the plugin.
	 * @param   object   &$row      The item object.
	 * @param   mixed    &$params   The view params.
	 * @param   integer   $page     The 'page' number.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function addPaginationDescription($context, &$row, &$params, $page = 0)
	{
		if ($this->app->isClient('site'))
		{
			$option                = $this->app->input->get('option');
			$view                  = $this->app->input->get('view');
			$doc                   = Factory::getDocument();
			$paginationDescription = $doc->getMetaData('paginationDescription');
			$paginationDescription = (!empty($paginationDescription)) ? explode(' |;| ', $paginationDescription) : array();

			if ($context === 'com_content.category' && $option === 'com_content' && $view === 'category' && !empty($row->introtext))
			{
				$paginationDescription[] = $row->title;
			}

			if ($paginationDescription)
			{
				$doc->setMetaData('paginationDescription', implode(' |;| ', $paginationDescription));
			}
		}
	}

	/**
	 * Method to get content category limit.
	 *
	 * @return int content category page items limit.
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected function getContentCategoryLimit()
	{
		/* @var $app SiteApplication */
		$app    = $this->app;
		$params = $app->getParams();
		if ($app->input->get('layout') === 'blog' || $params->get('layout_type') === 'blog')
		{
			$limit = $params->get('num_leading_articles') + $params->get('num_intro_articles');
		}
		else
		{
			$itemid = $app->input->get('id', 0, 'int') . ':'
				. $app->input->get('Itemid', 0, 'int');
			$limit  = (int) $app->getUserStateFromRequest('com_content.category.list.' . $itemid . '.limit',
				'limit', $params->get('display_num'), 'uint');
		}

		return $limit;
	}

	/**
	 * Set pagination title and description.
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function onBeforeCompileHead()
	{
		if ($this->params->get('pagination_description', 0)) $this->setPaginationDescription();
		if ($this->params->get('pagination_title', 0)) $this->setPaginationTitle();
	}

	/**
	 * Set pagination page meta description.
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected function setPaginationDescription()
	{
		if ($this->app->isClient('site'))
		{
			/* @var HtmlDocument $doc */
			$doc                   = Factory::getDocument();
			$paginationDescription = $doc->getMetaData('paginationDescription');
			$paginationDescription = (!empty($paginationDescription)) ? explode(' |;| ', $paginationDescription) : array();
			if ($this->app->input->getInt('start') && $paginationDescription)
			{
				// Set description
				$descriptionStart   = $doc->getTitle() . ': ';
				$descriptionEnd     = ' ...';
				$descriptionEndShow = false;
				$descriptionTotal   = iconv_strlen($descriptionStart) + iconv_strlen($descriptionEnd);
				$descriptionMax     = 250;

				$descriptionMiddle = array();
				foreach ($paginationDescription as $title)
				{
					$descriptionTotalNew = $descriptionTotal + iconv_strlen($title) + 2;
					if ($descriptionTotalNew <= $descriptionMax)
					{
						$descriptionTotal    = $descriptionTotalNew;
						$descriptionMiddle[] = $title;
					}
					else
					{
						$descriptionEndShow = true;
						break;
					}
				}

				$description = $descriptionStart . implode(', ', $descriptionMiddle);
				if ($descriptionEndShow)
				{
					$description .= $descriptionEnd;
				}

				$doc->setDescription($description);
			}

			if (isset($doc->_metaTags['name']) && isset($doc->_metaTags['name']['paginationDescription']))
			{
				$headData = $doc->getHeadData();
				unset($headData['metaTags']['name']['paginationDescription']);
				$doc->setHeadData($headData);
			}
		}
	}

	/**
	 * Add pagination page number to title.
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected function setPaginationTitle()
	{
		if ($this->app->isClient('site'))
		{
			$option = $this->app->input->get('option');
			$view   = $this->app->input->get('view');
			if ($offset = $this->app->input->getInt('start'))
			{
				$page = 0;
				if ($option == 'com_content' && $view === 'category')
				{
					$limit = $this->getContentCategoryLimit();
					$page  = $offset / $limit;
					if (is_float($page)) $page = floor($page);
					$page++;
				}

				if ($page > 1)
				{
					$doc = Factory::getDocument();
					$doc->setTitle(Text::sprintf('PLG_SYSTEM_ZNATOK_PAGINATION_TITLE', $doc->getTitle(), $page));
				}
			}
		}
	}

	/**
	 * Set canonical link.
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function onAfterRender()
	{
		$this->setCanonical();
	}

	/**
	 * Method to set correct canonical link to page.
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected function setCanonical()
	{
		if ($this->app->isClient('site') && $this->canonical)
		{
			$body = $this->app->getBody();
			if (preg_match('|<head>(.*)</head>|si', $body, $matches))
			{
				$search  = $matches[1];
				$replace = $search;

				// Remove old canonical
				$replace = preg_replace('#<link.*rel="canonical".*>#i', '', $replace);
				$replace = preg_replace('#<link.*rel="canonical">#i', '', $replace);

				// Add canonical
				$replace .= PHP_EOL . '	<link href="' . $this->canonical . '" rel="canonical" />' . PHP_EOL;

				// Remove empty lines
				$replace = preg_replace('#(</.*?>|/>)(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+#',
					'${1}' . PHP_EOL, $replace);

				// Replace body
				$body = str_replace($search, $replace, $body);
			}

			$this->app->setBody($body);
		}
	}
}