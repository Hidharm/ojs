<?php

/**
 * @file SubmissionReviewHandler.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionReviewHandler
 * @ingroup pages_reviewer
 *
 * @brief Handle requests for submission tracking. 
 */

// $Id$

import('pages.reviewer.ReviewerHandler');

class SubmissionReviewHandler extends ReviewerHandler {
	/** submission associated with the request **/
	var $submission;
	
	/** user associated with the request **/
	var $user;
		
	/**
	 * Constructor
	 **/
	function SubmissionReviewHandler() {
		parent::ReviewerHandler();
	}

	/**
	 * Display the submission review page.
	 * @param $args array
	 */
	function submission($args) {
		$journal = &Request::getJournal();
		$reviewId = $args[0];

		$this->validate($reviewId);
		$user =& $this->user;
		$submission =& $this->submission;

		$reviewAssignmentDao = &DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewAssignment = $reviewAssignmentDao->getReviewAssignmentById($reviewId);

		$reviewFormResponseDao =& DAORegistry::getDAO('ReviewFormResponseDAO');

		if ($submission->getDateConfirmed() == null) {
			$confirmedStatus = 0;
		} else {
			$confirmedStatus = 1;
		}

		$this->setupTemplate(true, $reviewAssignment->getArticleId(), $reviewId);

		$templateMgr = &TemplateManager::getManager();

		$templateMgr->assign_by_ref('user', $user);
		$templateMgr->assign_by_ref('submission', $submission);
		$templateMgr->assign_by_ref('reviewAssignment', $reviewAssignment);
		$templateMgr->assign('confirmedStatus', $confirmedStatus);
		$templateMgr->assign('declined', $submission->getDeclined());
		$templateMgr->assign('reviewFormResponseExists', $reviewFormResponseDao->reviewFormResponseExists($reviewId));
		$templateMgr->assign_by_ref('reviewFile', $reviewAssignment->getReviewFile());
		$templateMgr->assign_by_ref('reviewerFile', $submission->getReviewerFile());
		$templateMgr->assign_by_ref('suppFiles', $submission->getSuppFiles());
		$templateMgr->assign_by_ref('journal', $journal);
		$templateMgr->assign_by_ref('reviewGuidelines', $journal->getLocalizedSetting('reviewGuidelines'));

		import('submission.reviewAssignment.ReviewAssignment');
		$templateMgr->assign_by_ref('reviewerRecommendationOptions', ReviewAssignment::getReviewerRecommendationOptions());

		$templateMgr->assign('helpTopicId', 'editorial.reviewersRole.review');		
		$templateMgr->display('reviewer/submission.tpl');
	}

	/**
	 * Confirm whether the review has been accepted or not.
	 * @param $args array optional
	 */
	function confirmReview($args = null) {
		$reviewId = Request::getUserVar('reviewId');
		$declineReview = Request::getUserVar('declineReview');

		$reviewerSubmissionDao = &DAORegistry::getDAO('ReviewerSubmissionDAO');

		$this->validate($reviewId);
		$reviewerSubmission =& $this->submission;

		$this->setupTemplate();

		$decline = isset($declineReview) ? 1 : 0;

		if (!$reviewerSubmission->getCancelled()) {
			if (ReviewerAction::confirmReview($reviewerSubmission, $decline, Request::getUserVar('send'))) {
				Request::redirect(null, null, 'submission', $reviewId);
			}
		} else {
			Request::redirect(null, null, 'submission', $reviewId);
		}
	}

	/**
	 * Save the competing interests statement, if allowed.
	 */
	function saveCompetingInterests() {
		$reviewId = Request::getUserVar('reviewId');
		$this->validate($reviewId);
		$reviewerSubmission =& $this->submission;

		if ($reviewerSubmission->getDateConfirmed() && !$reviewerSubmission->getDeclined() && !$reviewerSubmission->getCancelled() && !$reviewerSubmission->getRecommendation()) {
			$reviewerSubmissionDao =& DAORegistry::getDAO('ReviewerSubmissionDAO');
			$reviewerSubmission->setCompetingInterests(Request::getUserVar('competingInterests'));
			$reviewerSubmissionDao->updateReviewerSubmission($reviewerSubmission);
		}

		Request::redirect(null, 'reviewer', 'submission', array($reviewId));
	}

	/**
	 * Record the reviewer recommendation.
	 */
	function recordRecommendation() {
		$reviewId = Request::getUserVar('reviewId');
		$recommendation = Request::getUserVar('recommendation');

		$this->validate($reviewId);
		$reviewerSubmission =& $this->submission;

		$this->setupTemplate(true);

		if (!$reviewerSubmission->getCancelled()) {
			if (ReviewerAction::recordRecommendation($reviewerSubmission, $recommendation, Request::getUserVar('send'))) {
				Request::redirect(null, null, 'submission', $reviewId);
			}
		} else {
			Request::redirect(null, null, 'submission', $reviewId);
		}
	}

	/**
	 * View the submission metadata
	 * @param $args array
	 */
	function viewMetadata($args) {
		$reviewId = $args[0];
		$articleId = $args[1];

		$this->validate($reviewId);
		$reviewerSubmission =& $this->submission;

		$this->setupTemplate(true, $articleId, $reviewId);

		ReviewerAction::viewMetadata($reviewerSubmission, ROLE_ID_REVIEWER);
	}

	/**
	 * Upload the reviewer's annotated version of an article.
	 */
	function uploadReviewerVersion() {
		$reviewId = Request::getUserVar('reviewId');

		$this->validate($reviewId);
		$this->setupTemplate(true);
		
		ReviewerAction::uploadReviewerVersion($reviewId);
		Request::redirect(null, null, 'submission', $reviewId);
	}

	/*
	 * Delete one of the reviewer's annotated versions of an article.
	 */
	function deleteReviewerVersion($args) {		
                $reviewId = isset($args[0]) ? (int) $args[0] : 0;
		$fileId = isset($args[1]) ? (int) $args[1] : 0;
		$revision = isset($args[2]) ? (int) $args[2] : null;

		$this->validate($reviewId);
		$reviewerSubmission =& $this->submission;

        if (!$reviewerSubmission->getCancelled()) ReviewerAction::deleteReviewerVersion($reviewId, $fileId, $revision);
		Request::redirect(null, null, 'submission', $reviewId);
	}

	//
	// Misc
	//

	/**
	 * Download a file.
	 * @param $args array ($articleId, $fileId, [$revision])
	 */
	function downloadFile($args) {
		$reviewId = isset($args[0]) ? $args[0] : 0;
		$articleId = isset($args[1]) ? $args[1] : 0;
		$fileId = isset($args[2]) ? $args[2] : 0;
		$revision = isset($args[3]) ? $args[3] : null;

		$this->validate($reviewId);
		$reviewerSubmission =& $this->submission;

		if (!ReviewerAction::downloadReviewerFile($reviewId, $reviewerSubmission, $fileId, $revision)) {
			Request::redirect(null, null, 'submission', $reviewId);
		}
	}

	//
	// Review Form
	//

	/**
	 * Edit or preview review form response.
	 * @param $args array
	 */
	function editReviewFormResponse($args) {
		$reviewId = isset($args[0]) ? $args[0] : 0;
		
		$this->validate($reviewId);
		$reviewerSubmission =& $this->submission;

		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewAssignment =& $reviewAssignmentDao->getReviewAssignmentById($reviewId);
		$reviewFormId = $reviewAssignment->getReviewFormId();
		if ($reviewFormId != null) {
			ReviewerAction::editReviewFormResponse($reviewId, $reviewFormId);		
		}
	}

	/**
	 * Save review form response
	 * @param $args array
	 */
	function saveReviewFormResponse($args) {
		$reviewId = isset($args[0]) ? $args[0] : 0;
		$reviewFormId = isset($args[1]) ? $args[1] : 0;
		if (ReviewerAction::saveReviewFormResponse($reviewId, $reviewFormId)) {
					Request::redirect(null, null, 'submission', $reviewId);
		}
	}

	//
	// Validation
	//

	/**
	 * Validate that the user is an assigned reviewer for
	 * the article.
	 * Redirects to reviewer index page if validation fails.
	 */
	function validate($reviewId) {
		$reviewerSubmissionDao = &DAORegistry::getDAO('ReviewerSubmissionDAO');
		$journal = &Request::getJournal();
		$user = &Request::getUser();

		$isValid = true;
		$newKey = Request::getUserVar('key');

		$reviewerSubmission = &$reviewerSubmissionDao->getReviewerSubmission($reviewId);

		if (!$reviewerSubmission || $reviewerSubmission->getJournalId() != $journal->getJournalId()) {
			$isValid = false;
		} elseif ($user && empty($newKey)) {
			if ($reviewerSubmission->getReviewerId() != $user->getUserId()) {
				$isValid = false;
			}
		} else {
			$user =& SubmissionReviewHandler::validateAccessKey($reviewerSubmission->getReviewerId(), $reviewId, $newKey);
			if (!$user) $isValid = false;
		}

		if (!$isValid) {
			Request::redirect(null, Request::getRequestedPage());
		}

		$this->submission =& $reviewerSubmission;
		$this->user =& $user;
		return true;
	}
}
?>
