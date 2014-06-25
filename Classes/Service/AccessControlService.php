<?php
/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Service for access control related stuff
 *
 * @package TYPO3
 * @subpackage tx_news
 */
class Tx_News_Service_AccessControlService {

	/**
	 * Check if a user has access to all categories of a news record
	 *
	 * @param array $newsRecord
	 * @return boolean
	 */
	public static function userHasCategoryPermissionsForRecord(array $newsRecord) {
		/** @var \TYPO3\CMS\Core\Authentication\BackendUserAuthentication $backendUserAuthentication */
		$backendUserAuthentication = $GLOBALS['BE_USER'];
		if ($backendUserAuthentication->isAdmin()) {
			// an admin may edit all news
			return TRUE;
		}

		// If there are any categories with denied access, the user has no permission
		if (count(self::getAccessDeniedCategories($newsRecord))) {
			return FALSE;
		} else {
			return TRUE;
		}
	}

	/**
	 * Get an array with the uid and title of all categories the user doesn't have access to
	 *
	 * @param array $newsRecord
	 * @return array
	 */
	public static function getAccessDeniedCategories(array $newsRecord) {
		/** @var \TYPO3\CMS\Core\Authentication\BackendUserAuthentication $backendUserAuthentication */
		$backendUserAuthentication = $GLOBALS['BE_USER'];
		if ($backendUserAuthentication->isAdmin()) {
			// an admin may edit all news
			return TRUE;
		}

		$backendUserCategories = $backendUserAuthentication->getCategoryMountPoints();
		$newsRecordCategories = self::getCategoriesForNewsRecord($newsRecord);

		// Remove categories the user has access to
		foreach ($newsRecordCategories as $key => $newsRecordCategory) {
			if (in_array($newsRecordCategory['uid'], $backendUserCategories)) {
				unset($newsRecordCategories[$key]);
			}
		}

		return $newsRecordCategories;
	}

	/**
	 * Get all categories for a news record respecting l10n_mode
	 *
	 * @param array $newsRecord
	 * @return array
	 */
	public static function getCategoriesForNewsRecord($newsRecord) {
		// determine localization overlay mode to select categories either from parent or localized record
		if ($newsRecord['sys_language_uid'] > 0 && ['l10n_parent'] > 0) {
			// localized version of a news record
			$categoryL10nMode = $GLOBALS['TCA']['tx_news_domain_model_news']['columns']['categories']['l10n_mode'];
			if ($categoryL10nMode === 'mergeIfNotBlank') {
				// mergeIfNotBlank: If there are categories in the localized version, take these, if not, inherit from parent
				$whereClause = 'tablenames=\'tx_news_domain_model_news\' AND uid_foreign=' . $newsRecord['uid'];
				$newsRecordCategoriesCount = $GLOBALS['TYPO3_DB']->exec_SELECTcountRows('*', 'sys_category_record_mm', $whereClause, '', '', '', 'uid_local');
				if ($newsRecordCategoriesCount > 0) {
					// take categories from localized version
					$newsRecordUid = $newsRecord['uid'];
				} else {
					// inherit categories from parent
					$newsRecordUid = $newsRecord['l10n_parent'];
				}
			} elseif ($categoryL10nMode === 'exclude') {
				// exclude: The localized version inherits the categories of the parent
				$newsRecordUid = $newsRecord['l10n_parent'];
			} else {
				// noCopy/prefixLangTitle: no inheritance
				$newsRecordUid = $newsRecord['uid'];
			}
		} else {
			$newsRecordUid = $newsRecord['uid'];
		}

		$whereClause = 'AND sys_category_record_mm.tablenames=\'tx_news_domain_model_news\' AND sys_category_record_mm.uid_foreign=' . $newsRecordUid .
			\TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('sys_category') . \TYPO3\CMS\Backend\Utility\BackendUtility::BEenableFields('sys_category');

		$res = $GLOBALS['TYPO3_DB']->exec_SELECT_mm_query(
			'sys_category_record_mm.uid_local, sys_category.title',
			'sys_category',
			'sys_category_record_mm',
			'tx_news_domain_model_news',
			$whereClause
		);

		$categories = array();
		while (($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))) {
			$categories[] = array(
				'uid' => $row['uid_local'],
				'title' => $row['title']
			);
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
		return $categories;

	}

}