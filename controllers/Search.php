<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

use Ramblur\Search\SearchException;
use Ramblur\Search\Pagination\SearchPaginationHelper;
use Ramblur\Search\ResultProcessors\SortResultsBySubjectPopularity;
use Ramblur\CIUtils\ConfigHelper;
use Ramblur\Search\Result\ResultsCol;

class search extends CI_Controller {

	private $configHelper;
	private $maxTotalDisplayedResultCount;
	private $maxTotalSearchResultCount;
	private $maxResultsPerPage;
	private $maxQueryStringLength;

	public function __construct() {
		parent::__construct();
		$this->config->load('search');
		$this->configHelper = new ConfigHelper($this);
		$this->maxResultsPerPage = $this->configHelper->getConfig("max_results_per_page", 25);
		$this->maxTotalDisplayedResultCount = $this->configHelper->getConfig("max_display_count", 1000);
		$this->maxQueryStringLength = $this->configHelper->getConfig("max_query_string_length", 150);
		$this->maxTotalSearchResultCount = $this->configHelper->getConfig("max_search_result_count", 1000);
	}

	private $errorMsg = false;

	public function index() {

		$data['referrer'] = 'search';
		$this->load->model('user');
		$this->load->model('group');
		$this->load->library('form_validation');
		$userId = $this->user->authCheck();
		$data["userId"] = $userId;

		$data['title'] = 'search ramblur';

		$this->load->model('globalSearch');
		$params = $this->input->get();

		$data["query"] = null;
		$data["safeOptions"] = null;
		$data["collection"] = null;
		$data["forumType"] = null;
		$data["content"] = null;
		$data["contentType"] = null;
		$data["underAge"] = $userId && $this->user->isUnder18($userId);

		$data["nextUrl"] = null;
		$data["previousUrl"] = null;

		$data['offset'] = 0;
		$data['totalResultCount'] = null;
		$data['maxAllowedPageResultCount'] = $this->maxResultsPerPage;
		$data['maxTotalDisplayedResultCount'] = $this->maxTotalDisplayedResultCount;

		$data['searchResults'] = [];

		if ($this->input->get()) {

			$offset = $this->input->get("offset") ?? 0;
			Logger::getLogger()->addDebug("offset", [$offset]);

			$data["query"] = trim($this->input->get("query"));

			//safe search options
			$data["safeOptions"] = $this->input->get("safeSearch");

			//dropdowns
			$data["collection"] = $this->input->get("collection");
			$data["forumType"] = $this->input->get("forumType");
			$data["content"] = $this->input->get("content");
			$data["contentType"] = $this->input->get("contentType");
			$data['offset'] = $offset;

			$fullResultSet = $this->getSearchResults($userId, $this->isAdultSearch());

			$data['totalResultCount'] = $fullResultSet->getTotal();

			$paginator = new SearchPaginationHelper($this->maxResultsPerPage, $fullResultSet);
			try {
				// Don't allow navigation past the result count
				if ($offset > $fullResultSet->getTotal()) {
					throw new Ramblur\Search\Pagination\PaginationException("Attempted to navigate past end of results");
				}

				$currentResultSet = $paginator->getSliceFromOffset((int) $offset);
				Logger::getLogger()->addDebug("corrected results count", [$currentResultSet->getTotal()]);

				$data['searchResults'] = $currentResultSet;

				//Offsets				
				//Get the next and previous offsets
				$data["nextOffset"] = $paginator->getNextOffset($this->maxResultsPerPage);
				$data["previousOffset"] = $paginator->getPreviousOffset($this->maxResultsPerPage);

				//Build the next URL
				if ($fullResultSet->getTotal() >= $this->maxResultsPerPage && $data["nextOffset"] + 1 < $fullResultSet->getTotal()) {
					$data["nextUrl"] = $this->globalSearch->buildNextUrl($params, $paginator->getCurrentEndHash(), $data["nextOffset"]);
				}

				//Build the previous URL
				if ($offset > 0) {
					$data['previousUrl'] = $this->globalSearch->buildPrevUrl($params, $paginator->getCurrentStartHash(), $data["previousOffset"]);
				}
			} catch (Ramblur\Search\Pagination\PaginationException $e) {
				Logger::getLogger()->addAlert($e->getMessage());
				$this->setErrorMessage("No results found");
				$data['searchResults'] = [];
			}

			// Build page title
			if ($this->input->get('browser')) {
				$data['title'] = 'browse ramblur forums';
			} else if ($data['query']) {
				$data['title'] = $data['query'] . ' - ' . $data['title'];
			}
		}

		if ($this->errorMsg !== false) {
			$data['errorMsg'] = $this->errorMsg;
		}
		$this->load->view('globalSearch/globalSearch', $data);
		$this->load->view('generic-footer', ['referrer' => 'search']);
	}

	private function getSearchResults($userId, $isAdultSearch) {
		$this->load->model('user');
		$this->load->model('group');
		$this->load->model("post");
		$this->load->model('globalSearch');
		try {

			// Check if the user is allowed to search unsafe
			if ($isAdultSearch && !$this->globalSearch->userCanSearchAdultContent($userId)) {
				Logger::getLogger()->addInfo("User is under 18, not searching for porn", [$userId, $this->input->get()]);
				return $this->globalSearch->buildSearchResults([]);
			}

			//Query Sphinx			
			Logger::getLogger()->addDebug("Running sphinx query", [$this->input->get(), $userId]);
			$sphinxQuery = $this->globalSearch->runSearchQuery($this->input->get(), $userId, $this->maxQueryStringLength, $this->maxTotalSearchResultCount);
			$sphinxResults = $sphinxQuery;
			//Convert the results into the result object framework
			Logger::getLogger()->addDebug("Sphinx results count", [count($sphinxResults)]);

			$sorter = null;
			if ($this->input->get('browser')) {
				$sorter = new SortResultsBySubjectPopularity();
			}
			$results = $this->globalSearch->buildSearchResults($sphinxResults, $sorter);
			Logger::getLogger()->addDebug("Post Processed results count", [count($sphinxResults)]);
			//Logger::getLogger()->info(json_encode($results, JSON_PRETTY_PRINT));

			return $results;
		} catch (\Exception $e) {
			$this->setErrorMessage("There was a problem retrieving your seach results.");
			Logger::getLogger()->addCritical($e->getMessage(), $e->getTrace());
			// On death return an empty result set
			return new ResultsCol(new \Ds\Vector());
		}
	}

	private function setErrorMessage($msg) {
		if ($this->errorMsg === false) {
			$this->errorMsg = '';
		}
		$this->errorMsg .= $msg . ' ';
	}

	/**
	 * Check if this is an adult search by looking at the input params
	 * @return boolean
	 */
	private function isAdultSearch() {
		if ($this->input->get("safeSearch") !== null && $this->input->get("safeSearch") == "unsafe") {
			return true;
		} elseif ($this->input->get("forumType") !== null && $this->globalSearch->isSearchingClub18(json_decode($this->input->get("forumType")))) {
			return true;
		}
		return false;
	}

}
