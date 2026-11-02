<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

use Entities\Forum\Forum;
use Entities\Forum\ForumAttribute;
use Entities\Forum\ForumAttributes;
use Entities\Forum\ForumVisibility;
use Entities\Forum\Visibility;

class SubjectAttribute extends CI_Model {

	private $tableName = 'SubjectAttributes';
	private $lookupTableName = 'SubjectAttributeToSubject';

	/** @var ForumAttributes */
	private $allAttributes = null;
	private $logger;

	/**
	 * @todo - rename/move enum classes to make it clear they're not database representations
	 */
	function __construct() {
		parent::__construct();
		$this->load->library('logging/LoggerFactory', []);
		$this->config->load("logging");
		$this->logger = LoggerFactory::makeLogger($this->config->item("logging_default_logger"), "FILE");
	}

	/**
	 * Retrieves attribute data as an associative array for the requested subject.
	 * 
	 * @param int|string $subjectId
	 * @param array $subjectData
	 * @param bool $propertiesFormat
	 * @return array
	 */
	public function getSubjectAttributesArray($subjectId) {
		$forum = $this->initialiseForum($subjectId);
		return $forum->getAttributesArray();
	}

	/**
	 * Returns an instance of a Forum class.
	 * @param int|string $subjectId
	 * @return \Entities\Forum\Forum
	 */
	public function getForumEntity($subjectId) {
		return $this->initialiseForum($subjectId);
	}

	/**
	 * Returns the visibility of the forum: private, public or closed.
	 * 
	 * @param int|string $subjectId
	 * @param boolean $private - optional. If not provided, database will be queried for the private value
	 * @return string
	 */
	public function getForumVisibility($subjectId, $private = null) {
		$forum = $this->getForumEntity($subjectId);
		if (is_null($private)) {
			$private = $this->group->getForumPrivate($subjectId);
		}
		return ForumVisibility::getVisibilityValue($forum, $private);
	}

	/**
	 * Returns the database value for the field Subject.private based on the provided
	 * visibility.
	 * 
	 * @param Visibility $visibility
	 * @return boolean
	 */
	public function getForumPrivateFromVisibility(Visibility $visibility) {
		return ForumVisibility::getPrivateValue($visibility);
	}

	/**
	 * Inserts database rows for a forum based on the given visibility.
	 * 
	 * @param int|string $subjectId
	 * @param Visibility $visibility
	 */
	public function insertAttributesByVisibility($subjectId, Visibility $visibility) {
		
		$this->logger->addInfo("Attempting to insert forum visibility attributes ", [$subjectId, $visibility]);
		
		$forum = $this->getForumEntity($subjectId);
		$forum->setVisibility($visibility);
		$attrsByVisibility = $forum->getVisibilityAttributes($this->getAll());

		$insertData = [];
		foreach ($attrsByVisibility->getCollection() as $a) {
			$insertData[] = ['subjectId' => $subjectId, 'attributeId' => $a->id];
		}
		if (count($insertData) > 0) {
			$this->logger->addInfo("Inserting visibility attributes", [$subjectId, $insertData]);
			$this->db->insert_batch($this->lookupTableName, $insertData);
		}
		return $this->db->affected_rows() > 0;
	}

	/**
	 * 
	 * Updates the given forum to the given Visibility state
	 * Clears all existing visibility attributes first - not perfect
	 * 
	 * 
	 * @param type $subjectId
	 * @param Visibility $visibility
	 * @return type
	 */
	public function updateAttributesByVisibility($subjectId, Visibility $visibility) {
		$this->logger->addInfo("Updating forum visibility attrs", [$subjectId, $visibility]);
		$forumVisibility = new ForumVisibility($visibility);
		$forumVisibilityAttributes = $this->getByNames($forumVisibility->getAllUniqueAttributesArray());

		if (!$forumVisibilityAttributes->isEmpty()) {
			$this->removeAttributes($subjectId, $forumVisibilityAttributes);
		}
		return $this->insertAttributesByVisibility($subjectId, $visibility);
	}

	/**
	 * Removes given attributes from a forum
	 * @param unknown $subjectId
	 * @param ForumAttributes $attrs
	 */
	public function removeAttributes($subjectId, ForumAttributes $attrs) {
		$this->logger->addInfo("Removing forum visibility attrs from the forum", [$subjectId, $attrs->getAllValues()]);
		$attrIds = [];
		foreach ($attrs->getCollection() as $a) {
			$attrIds[] = $a->id;
		}
		$this->db->where("subjectId", $subjectId);
		$this->db->where_in('attributeId', $attrIds);
		$this->db->delete($this->lookupTableName);
		return $this->db->affected_rows() > 0;
	}

	/**
	 * Sets up a forum class, injecting a collection of all forum attributes available, as well
	 * as a collection of all attributes for that forum.
	 * @param int|string $subjectId
	 * @return \Entities\Forum\Forum
	 */
	private function initialiseForum($subjectId) {
		$forum = new Forum($subjectId);
		$forum->setAttributes($this->getBySubject($subjectId));
		return $forum;
	}

	/**
	 * Gets all available forum attributes.
	 * @return ForumAttributes
	 */
	private function getAll(): ForumAttributes {

		if (is_null($this->allAttributes)) {
			$this->allAttributes = ForumAttributes::fromArray($this->db->get($this->tableName)->result());
		}
		return $this->allAttributes;
	}

	/**
	 * Gets all attributes for the given forum.
	 * @param int|string $subjectId
	 * @return ForumAttributes
	 */
	private function getBySubject($subjectId): ForumAttributes {

		$this->db->select('a.*')
				->from("$this->lookupTableName AS s")
				->join("$this->tableName AS a", "a.id = s.attributeId")
				->where('s.subjectId', $subjectId);
		return ForumAttributes::fromArray($this->db->get()->result());
	}

	/**
	 * Gets all attributes in the given array
	 * @param array $attr
	 * @return ForumAttributes
	 */
	private function getByNames(array $attr): ForumAttributes {
		$this->db->select('*')
				->from($this->tableName)
				->where_in('name', $attr);
		return ForumAttributes::fromArray($this->db->get()->result());
	}

}
