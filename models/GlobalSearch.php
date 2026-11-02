<?php

use Foolz\SphinxQL\SphinxQL;
use Ramblur\Search\QueryBuilder;
use Ramblur\Search\Filters\ForumAccessFilter;
use Ramblur\Search\Result\ResultsCol;
use Ramblur\Search\Result\ResultsBuilder;
use Ramblur\Search\ResultProcessors\ResultCollectionProcessorDirector;
use Ramblur\Search\ResultProcessors\PopulateSubjectData;
use Ramblur\Search\ResultProcessors\PopulateFollowingData;
use Ramblur\Search\ResultProcessors\PopulateAttachmentData;
use Ramblur\Search\ResultProcessors\RemoveUnauthorisedResults;
use Ramblur\Search\ResultProcessors\PopulateCategoryData;
use Ramblur\Search\ResultProcessors\PopulateSubjectStats;
use Ramblur\Search\ResultProcessors\PopulateEventData;
use Ramblur\Search\ResultProcessors\SortResultsByWeight;
use Ramblur\Search\ResultProcessors\PopulateThumbnailData;
use Ramblur\Search\Filters\SafeSearchFilter;
use Ramblur\Search\Filters\ParamFilterBuilder;

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

/**
 * Model for all Global Search operations
 */
class GlobalSearch extends CI_Model {

	const SEARCH_RESULT_CACHE_TIMEOUT = 600; //seconds to cache search results for

	function __construct() {
		// Call the Model constructor
		parent::__construct();
	}

	function setGlobalSearchSafetyLevel($userId, $safetyLevel) {

		if (!$this->user->canManageUser($this->user->authCheck(), $userId)) {
			return false;
		}

		$safetyLevelId = $this->config->item($safetyLevel);
		if (!$safetyLevelId) {
			return false;
		}

		$this->db->select('id')
				->from('UserToGlobalSearch')
				->where('userId', $userId);

		if ($this->db->get()->num_rows() > 0) {
			$this->db->set('id', $safetyLevelId)
					->where('userId', $userId)
					->update('UserToGlobalSearch');
		} else {
			$this->db->insert('UserToGlobalSearch', array('userId' => $userId, 'id' => $safetyLevelId));
		}
		return true;
	}

	/**
	 * Returns the users safety level
	 * @param type $userId
	 * @return boolean
	 */
	function getGlobalSearchSafetyLevel($userId) {
		if (!$this->user->canManageUser($this->user->authCheck(), $userId)) {
			return false;
		}

		$this->db->select('id')
				->from('UserToGlobalSearch')
				->where('userId', $userId);

		$q = $this->db->get();
		if ($q->num_rows() > 0) {
			switch ($q->row()->id) {
				case 1:
					return SafeSearchFilter::SAFE;
					break;
				case 2:
					return SafeSearchFilter::MODERATE;
					break;
				case 3:
					return SafeSearchFilter::UNSAFE;
					break;
				default:
					return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Check if a user is allowed to search unsafe mode
	 * 
	 * Logged out users and logged in over 18 users can
	 * 
	 * @param type $userId
	 * @return boolean
	 */
	public function userCanSearchUnsafeMode($userId = null) {
		$this->load->model("user");
		if (!$userId || !$this->user->isUnder18($userId)) {
			return true;
		}

		return false;
	}

	/**
	 * Check if user can seach club 18 forums
	 * @param type $userId
	 * @return boolean
	 */
	public function userCanSearchClub18($userId = null) {
		$this->load->model("user");
		if (!$userId || !$this->user->isUnder18($userId)) {
			return true;
		}

		return false;
	}

	/**
	 * Check if user can search adult content
	 * 
	 * @param type $userId
	 */
	public function userCanSearchAdultContent($userId = null) {
		return $this->userCanSearchUnsafeMode($userId) && $this->userCanSearchClub18($userId);
	}

	/**
	 * 
	 * @param array $forumTypes
	 * @return boolean
	 */
	public function isSearchingClub18(array $forumTypes) {
		foreach ($forumTypes as $forumType) {
			if (strtolower($forumType) === 'club18') {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns the safe mode cookie
	 * @return type
	 */
	public function getSessionSafeMode() {
		$this->load->helper('cookie');
		return $this->input->cookie("currentSafetyLevel");
	}

	/**
	 * Runs a sphinx query from the given search parameters.
	 * @param array $params
	 * @return Foolz\SphinxQL\Drivers\Mysqli\ResultSet
	 */
	public function runSearchQuery(array $params, $userId, int $maxQueryStringLength, int $maxSearchResults) {
		$this->load->model('user');
		$logger = Logger::getLogger();

		$queryString = $params['query'] ?? "";

		$sphinx = (new Ramblur\Search\SphinxFactory())
				->buildSphinx($this->config->item('sphinxhostname'), $this->config->item('sphinxport'));

		$builder = (new \Ramblur\Search\QueryBuilderBuilder($sphinx, $logger))
				->setQuery($queryString)
				->setParams($params)
				->setMaxQueryStringLength($maxQueryStringLength)
				->setMaxSearchResults($maxSearchResults)
				->build();

		$builder->setFilters((new ParamFilterBuilder())->buildFilters($params, $builder->SphinxEscapeString($queryString)));
		// IMPORTANT!! - Explicitly add access control for forums		
		$builder->addFilter($this->getForumAccessFilter($queryString));

		//A safe search value must be given	to sphinx	
		if (!isset($params['safeSearch'])) {
			$builder->addFilter($this->getDefaultUserSearchModeFilter($userId, $queryString));
		}

		$this->load->driver('cache');
		if ($this->cache->memcached->is_supported()) {
			$builderHash = $userId . "-" . $builder->toHash();
			if (($cachedResults = $this->cache->memcached->get($builderHash))) {
				Logger::getLogger()->addInfo("cache hit", [$builderHash]);
				$sphinxResults = $cachedResults;
			} else {
				$sphinxResults = $builder->doSearchQuery()->fetchAllAssoc();
				$this->cache->memcached->save($builderHash, $sphinxResults, 60);
			}
		} else {
			$sphinxResults = $builder->doSearchQuery()->fetchAllAssoc();
		}

		return $sphinxResults;
	}

	/**
	 * Returns a users forum access filter
	 * @param type $queryString
	 * @return ForumAccessFilter
	 */
	public function getForumAccessFilter($queryString): ForumAccessFilter {
		
		// IMPORTANT!! - Explicitly add access control for forums
		if($this->user->adminCheck()) {
			$privateForumIds = $this->group->getPrivateForums();
		} else {
			$privateForumIds = $this->user->getPrivateFollowedSubjectIds($this->user->authCheck());
		}
		return new ForumAccessFilter($privateForumIds, $queryString);
	}

	/**
	 * Params are god, then database value, then cookie value
	 * @param type $userId
	 * @param type $queryString
	 * @return \ISearchFilter
	 */
	public function getDefaultUserSearchModeFilter($userId, $queryString): SafeSearchFilter {
		$storedSafeSearch = $this->getGlobalSearchSafetyLevel($userId);
		$cookieSafeSearch = $this->getSessionSafeMode();
		if ($storedSafeSearch) {
			$searchMode = $storedSafeSearch;
		} elseif ($cookieSafeSearch) {
			$searchMode = $cookieSafeSearch;
		} else {
			$searchMode = SafeSearchFilter::MODERATE;
		}

		if ($searchMode == SafeSearchFilter::UNSAFE && !$this->userCanSearchUnsafeMode($userId)) {
			$searchMode = SafeSearchFilter::MODERATE;
		}

		$safeSearchFilter = new SafeSearchFilter($searchMode, $queryString);
		Logger::getLogger()->addDebug("No safe search given, setting the search to safe in the model");
		return $safeSearchFilter;
	}

	/**
	 * Builds search results from the given Sphinx result 
	 * @param type $searchResult
	 * @return ResultsCol
	 */
	function buildSearchResults($searchResult, \Ramblur\Search\ResultProcessors\ResultsProcessor $sorter = null): ResultsCol {
		$this->load->model('user');
		$this->load->model('group');
		$this->load->model("post");
		$this->load->model("category");
		$this->load->model("node");
		$this->load->model("geo");
		$this->load->model("content");
		
		$userId = $this->user->authCheck();

		$resultBuilder = new ResultsBuilder($searchResult, Logger::getLogger());
		$resultsCol = $resultBuilder->build();
		//Logger::getLogger()->addInfo("Building search", [$searchResult, $resultsCol]);

		$resultsProcessor = new ResultCollectionProcessorDirector($resultsCol, Logger::getLogger());
		$resultsProcessor->addProcessor(new RemoveUnauthorisedResults($userId, $this->group));
		$resultsProcessor->addProcessor(new PopulateSubjectData($this->group));
		$resultsProcessor->addProcessor(new PopulateSubjectStats($this->group, $this->post));
		$resultsProcessor->addProcessor(new PopulateFollowingData($userId, $this->user));
		$resultsProcessor->addProcessor(new PopulateAttachmentData($this->content));
		$resultsProcessor->addProcessor(new PopulateCategoryData($this->category, $this->node));
		$resultsProcessor->addProcessor(new PopulateEventData($this->post, $this->geo));
		$resultsProcessor->addProcessor(new PopulateThumbnailData($this->content, $this->group));
		if (!$sorter) {
			$resultsProcessor->addProcessor(new SortResultsByWeight());
		} else {
			$resultsProcessor->addProcessor($sorter);
		}

		$processedResultsCol = $resultsProcessor->doProcessing();
		

		/*		 * $result = new Ramblur\Search\Result\Factories\GenericResultFactory() ;
		  $injectedResult = $result->buildResult([]);
		  $injectedResult->setContentId('8711')
		  ->setSubjectName('Injected Subject')
		  ->setSubjectDescription('Injected Subject Description')
		  ->setContent("Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.")
		  ->setSubjectVisibility(0)
		  ->setTitle("This Content is Injected")
		  ->setContentType(new \Ramblur\Search\ContentType('post'));

		  $processedResultsCol->insertResult($injectedResult, 0) ;* */

		//Logger::getLogger()->addDebug("Returned following results", [$processedResultsCol]);
		return $processedResultsCol;
	}

	public function getPaginatedSearchResults() {
		
	}

	public function buildNextURL($params, $endHash, $nextOffset) {
		$nextUrl = array_merge($params, ["hash" => $endHash, "state" => "next", "offset" => $nextOffset]);
		return current_url() . "?" . http_build_query($nextUrl);
	}

	public function buildPrevURL($params, $startHash, $prevOffset) {
		$previousUrl = array_merge($params, ["hash" => $startHash, "state" => "prev", "offset" => $prevOffset]);
		return current_url() . "?" . http_build_query($previousUrl);
	}

}
