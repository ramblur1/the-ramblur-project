<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

/**
 * Model for all content operations
 */
class Content extends CI_Model {

	function __construct() {
		parent::__construct();
	}

	/**
	 * Returns all of the Content rows for the given array of content ids (fileNames)
	 * Also returns an associated postId if the content has one
	 * @param type $contentIds
	 * @return boolean
	 */
	public function getContentWithPostIdFromContentIds($contentIds) {

		if (empty($contentIds)) {
			return false;
		}

		$content = [];
		foreach (array_chunk($contentIds, 200) as $chunkOfIds) {
			$this->db->select("Nodes.postId, Content.*")
					->from("Content")
					->Join('ContentToNodes', 'ContentToNodes.fileName = Content.fileName')
					->join('Nodes', 'ContentToNodes.nodeId = Nodes.nodeId')
					->where_in('Content.fileName', $chunkOfIds);
			$result = $this->db->get();

			if ($result->num_rows() > 0) {
				$content = array_merge($content, $result->result_array());
			}
		}

		if (!empty($content)) {
			return $content;
		}
		return false;
	}
	
	

	/**
	 * Searches for the first images, links or maps in the given posts
	 * 
	 * - concat 0 onto photos to ensure they come up first
	 * - contact the timestamp to order files by insertion date
	 * 
	 * @param type $postIds
	 * @return boolean
	 */
	public function getFirstAttachmentFromPostsByPostIds($postIds) {
		$sql = "SELECT
					postId,
					nodeId,
					SUBSTRING_INDEX( con, '||',-1) as fileName
				FROM
					(
						SELECT
							PostId,
							ContentToNodes.nodeId,
							MIN( CONCAT( 
										COALESCE(IF( Content.fileType = 'photo', '0', '99' ),''), '||', 
										COALESCE(UNIX_TIMESTAMP( timeStamp ),''), '||', 
										COALESCE(Content.fileName,'') )) as con
						FROM
							Content
						LEFT JOIN ContentToNodes ON
							ContentToNodes.fileName = Content.fileName
						LEFT JOIN Nodes ON
							ContentToNodes.nodeId = Nodes.nodeId
						WHERE
							Nodes.parentNodeId = 0
							AND fileType IN(
								'photo',
								'link',
								'map'
							)
						GROUP BY
							PostId,
							ContentToNodes.nodeId
						ORDER BY
							ContentToNodes.nodeId
			) as sub
			WHERE sub.postId IN ?
			";

		$content = [];
		foreach (array_chunk($postIds, 200) as $chunkOfIds) {
			$results = $this->db->query($sql, [$chunkOfIds])->result_array();
			if (count($results)) {
				$content = array_merge($content, $results);
			}
		}
		if (!empty($content)) {
			return $content;
		}
		return false;
	}

}
