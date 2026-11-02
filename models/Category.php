<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

/**
 * Model for all category operations
 * 
 * This is highly inefficient, scalable queries and models need to be built
 * 
 */
class Category extends CI_Model {

	//db limit for snv value for cats is 38 but setting to to 30 max
	const MAX_CATEGORY_DEPTH = 30; 

	function __construct() {
		// Call the Model constructor
		parent::__construct();
	}

	/**
	 * Gets categories for all of the given postIds, returns an row array keyed
	 * by postId
	 * @param type $postIds
	 * @return type
	 */
	public function getPostsCategories(array $postIds) {
		if(empty($postIds)){
			return false;
		}
		$this->load->model('post');

		$posts = $this->post->getPostsByIds($postIds);
		if (empty($posts)) {
			return [];
		}

		$categories = [];
		foreach ($posts as $post) {
			$postCat = $this->getCategory($post['categoryId']);
			$postCategories = $this->getParentCategories($postCat['parentCatId'], [$postCat]);
			krsort($postCategories);
			$categories[$post['postId']] = $postCategories;
		}

		return $categories;
	}

	/**
	 * Gets the categories for the given post
	 * @param type $postId
	 * @return type
	 */
	public function getPostCategories($postId) {
		return $this->getPostsCategories([$postId]) ;
	}

	public function getCategory($categoryId) {
		return $this->db->select('*')
						->from('Category')
						->where('categoryId', $categoryId)
						->get()->row_array();
	}

	/**
	 * Recursively walks up the category tree starting with the given parentCategoryId
	 * Could be better
	 * @param type $parentCategoryId
	 * @param type $cats
	 * @return type
	 */
	public function getParentCategories($parentCategoryId, $cats) {
		$parentCategory = $this->getCategory($parentCategoryId);
		$cats[] = $parentCategory;
		if ($parentCategory['level'] == 0) {
			return $cats;
		} else {
			$cats = $this->getParentCategories($parentCategory['parentCatId'], $cats);
			return $cats;
		}
		return $cats;
	}

	/* ADDED CJT */

	function getForumCategories($data) {
		$this->load->model('user');
		$userId = $this->user->authCheck();

		$query = "CALL getCategoryBranch(?, ?, ?, ?)";
		// 4 levels ???? - change to "unlimited"?
		$params = array(0, self::MAX_CATEGORY_DEPTH, $data['subjectId'], $userId);

		$query = $this->db->query($query, $params);

		if ($query->num_rows() > 0) {
			return $query->result_array();
			//$result = $query->result_array();
			//$cats = array();

			/*
			 * foreach ($result as $row) {

				$row['path'] = ($row['level'] > 1) ? $this->getForumCategoryPath($row['id'], $row['parent'], $row['level']) . ' / ' . $row['name'] : $row['path'] = $row['name'];
				//print_r($row);
				array_push($cats, $row);
			}
			* 
			*/
			//return $cats;
		}
		return '0';
	}

	function getForumCategoryPath($id, $parent, $level) {

		/*		 * ***************************************************************

		  DISCLAIMER: there may very well be a better way to get the "path"
		  of a category, but this was a) quicker & b) easier (for me).

		 * ************************************************************** */

		switch ($level) {

			case 4:

				// get parent (3), parent of parent (2), parent of parent parent (1)
				$sql = "SELECT parentCatId, name FROM Category WHERE categoryId = ?";
				$params = array($parent);
				$query = $this->db->query($sql, $params);
				$result = $query->row_array();
				$parent = $result['name'];

				$sql = "SELECT parentCatId, name FROM Category WHERE categoryId = ?";
				$params = array($result['parentCatId']);
				$query = $this->db->query($sql, $params);
				$result = $query->row_array();
				$parentparent = $result['name'];

				$sql = "SELECT name FROM Category WHERE categoryId = ?";
				$params = array($result['parentCatId']);
				$query = $this->db->query($sql, $params);
				$result = $query->row_array();
				$parentparentparent = $result['name'];

				$catpath = $parentparentparent . ' / ' . $parentparent . ' / ' . $parent;

				break;

			case 3:

				// get parent (2), parent of parent (1)
				$sql = "SELECT parentCatId, name FROM Category WHERE categoryId = ?";
				$params = array($parent);
				$query = $this->db->query($sql, $params);
				$result = $query->row_array();
				$parent = $result['name'];

				$sql = "SELECT name FROM Category WHERE categoryId = ?";
				$params = array($result['parentCatId']);
				$query = $this->db->query($sql, $params);
				$result = $query->row_array();
				$parentparent = $result['name'];

				$catpath = $parentparent . ' / ' . $parent;
				break;

			case 2:

				// get parent (1)
				$sql = "SELECT name FROM Category WHERE categoryId = ?";
				$params = array($parent);
				$query = $this->db->query($sql, $params);
				$result = $query->row_array();
				$catpath = $result['name'];
				break;
		}

		return $catpath;
	}

}
