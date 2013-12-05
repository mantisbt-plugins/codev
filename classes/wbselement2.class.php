<?php

class WBSElement2 extends Model {

   /**
    * @var Logger The logger
    */
   private static $logger;

   /**
    * Initialize complex static variables
    * @static
    */
   public static function staticInit() {
      self::$logger = Logger::getLogger(__CLASS__);
   }

   private $id;
   private $title;
   private $icon;
   private $font;
   private $color;
   private $bugId;
   private $parentId;
   private $rootId;
   private $order;

   private $isModified;

   /**
    *
    * @param int $id
    * @param String $title
    * @param int $bug_id
    * @param boolean $isFolder
    * @param int $parent_id
    * @param int $order
    * @param type $icon
    * @param type $font
    * @param type $color
    */
   public function __construct($id, $root_id = NULL, $bug_id = NULL, $parent_id = NULL, $order = NULL,
			  $title = NULL, $icon = NULL, $font = NULL, $color = NULL) {

      if (is_null($id)) {
         $this->id = self::create($bug_id, $parent_id, $root_id, $order, $title, $icon, $font, $color);
         $this->initialize();
      } else {
         $this->id = $id;

         // get (old) data from DB
         $this->initialize();

			// check $root_id
         if (($this->rootId != $root_id) && ($this->id != $root_id)) {
            $e = new Exception("Constructor: WBSElement $id exists with root_id = $this->rootId (expected $root_id)");
            self::$logger->error("EXCEPTION WBSElement constructor: " . $e->getMessage());
            self::$logger->error("EXCEPTION stack-trace:\n" . $e->getTraceAsString());
            throw $e;
         }

         // update data
			$isModified = false;
         if ($this->isFolder() && !is_null($title)) { $this->title = $title; }
         if (!is_null($parent_id)) { $this->parentId = $parent_id; $isModified = true;}
         if (!is_null($order))     { $this->order = $order; $isModified = true;}
         if (!is_null($icon))      { $this->icon = $icon; $isModified = true;}
         if (!is_null($font))      { $this->font = $font; $isModified = true;}
         if (!is_null($color))     { $this->color = $color; $isModified = true;}
			if ($isModified) {
				$this->update(); // TODO do it now ?
			}
      }
   }

   /**
    * Initialize
    * @param resource $row The issue details
    * @throws Exception If wbselement doesn't exists
    */
   public function initialize($row = NULL) {
      if ($row == NULL) {
         // Get info
         $query = "SELECT * FROM `codev_wbselement_table` " .
                 "WHERE id = $this->id";
         $result = SqlWrapper::getInstance()->sql_query($query);
         if (!$result) {
            echo "<span style='color:red'>ERROR: Query FAILED</span>";
            exit;
         }
         $row = SqlWrapper::getInstance()->sql_fetch_object($result);
      }

      if (NULL != $row) {
         $this->id = $row->id;
         $this->title = $row->title;
         $this->bugId = $row->bug_id;
         $this->parentId = $row->parent_id;
         $this->rootId = $row->root_id;
         $this->order = $row->order;
         $this->icon = $row->icon;
         $this->font = $row->font;
         $this->color = $row->color;
      } else {
         $e = new Exception("Constructor: WBSElement $this->id does not exist in Mantis DB.");
         self::$logger->error("EXCEPTION WBSElement constructor: " . $e->getMessage());
         self::$logger->error("EXCEPTION stack-trace:\n" . $e->getTraceAsString());
         throw $e;
      }
   }


   /**
    *
    * @param int $bug_id
    * @param int $parent_id
    * @param int $root_id
    * @param int $order
    * @param String $title
    * @param String $icon
    * @param String $font
    * @param String $color
    * @return int id
    */
   public static function create($bug_id, $parent_id, $root_id, $order, $title, $icon=NULL, $font=NULL, $color=NULL) {

		// --- check values
		if (!is_null($parent_id)) {
			// check parrent exists and is a folder
			$queryP = "SELECT bug_id FROM `codev_wbselement_table` WHERE id = $parent_id";
         $resultP = SqlWrapper::getInstance()->sql_query($queryP);
         if (!$resultP) {
            echo "<span style='color:red'>ERROR: Query FAILED</span>";
            exit;
         }
         $found  = (0 != SqlWrapper::getInstance()->sql_num_rows($resultP)) ? true : false;
			if (!$found) {
				$e = new Exception("create WBSElement: parrent_id $parent_id does not exist.");
				self::$logger->error("EXCEPTION: " . $e->getMessage());
				self::$logger->error("EXCEPTION stack-trace:\n" . $e->getTraceAsString());
				throw $e;
			}
			$rowP = SqlWrapper::getInstance()->sql_fetch_object($resultP);
			if (!is_null($rowP->bug_id)) {
				$e = new Exception("create WBSElement: parrent_id $parent_id should be a Folder (bug_id = $rowP->bug_id).");
				self::$logger->error("EXCEPTION: " . $e->getMessage());
				self::$logger->error("EXCEPTION stack-trace:\n" . $e->getTraceAsString());
				throw $e;
			}
		}
		if (is_null($bug_id)) {
			// new Folder (source: wbs_editor)
			if (is_null($title)) {
				$e = new Exception("create WBSElement: Folder needs a title.");
				self::$logger->error("EXCEPTION: " . $e->getMessage());
				self::$logger->error("EXCEPTION stack-trace:\n" . $e->getTraceAsString());
				throw $e;
			}
		} else {
			if (!Issue::exists($bug_id)) {
				$e = new Exception("create WBSElement: issue $bug_id does not exist in Mantis DB.");
				self::$logger->error("EXCEPTION: " . $e->getMessage());
				self::$logger->error("EXCEPTION stack-trace:\n" . $e->getTraceAsString());
				throw $e;
			}
			if (is_null($parent_id)) {
				$e = new Exception("create WBSElement: issue $bug_id must have a parent_id (Folder).");
				self::$logger->error("EXCEPTION: " . $e->getMessage());
				self::$logger->error("EXCEPTION stack-trace:\n" . $e->getTraceAsString());
				throw $e;
			}
			$title = null; // issue summary is stored in mantis_bug_table
		}

		if (is_null($order)) { $order = 1; }

		// --- insert new element
      $query  = 'INSERT INTO `codev_wbselement_table` (`order`';
		if (!is_null($bug_id)) { $query .= ', `bug_id`'; }
		if (!is_null($parent_id)) { $query .= ', `parent_id`'; }
		if (!is_null($root_id)) { $query .= ', `root_id`'; }
		if (!is_null($title)) { $query .= ', `title`'; }
		if (!is_null($icon)) { $query .= ', `icon`'; }
		if (!is_null($font)) { $query .= ', `font`'; }
		if (!is_null($color)) { $query .= ', `color`'; }
		$query .= ") VALUES ('$order'";
		if (!is_null($bug_id)) { $query .= ", '$bug_id'"; }
		if (!is_null($parent_id)) { $query .= ", '$parent_id'"; }
		if (!is_null($root_id)) { $query .= ", '$root_id'"; }
		if (!is_null($title)) { $query .= ", '$title'"; }
		if (!is_null($icon)) { $query .= ", '$icon'"; }
		if (!is_null($font)) { $query .= ", '$font'"; }
		if (!is_null($color)) { $query .= ", '$color'"; }
		$query .= ')';

      $result = SqlWrapper::getInstance()->sql_query($query);
      if (!$result) {
         echo "<span style='color:red'>ERROR: Query FAILED</span>";
         exit;
      }
      return SqlWrapper::getInstance()->sql_insert_id();
   }

   public function getChildrenIds() {
      $childrenIds = array();

      $query = "SELECT id FROM `codev_wbselement_table` ".
              "WHERE `parent_id` = $this->id ".
              //"AND bug_id IS NULL ".
              "AND root_id = $this->rootId ".
              "AND id <> $this->id ".
              "ORDER BY `order` ";
      $result = SqlWrapper::getInstance()->sql_query($query);
      if (!$result) {
         echo "<span style='color:red'>ERROR: Query FAILED</span>";
         exit;
      }
      while ($row = SqlWrapper::getInstance()->sql_fetch_object($result)) {
         $childrenIds[] = $row->id;
      }
      return $childrenIds;
   }

   public function delete($root_id) {

      if ($this->rootId != $root_id) {
            $e = new Exception("delete: WBSElement $id exists with root_id = $this->rootId (expected $root_id)");
            self::$logger->error("EXCEPTION WBSElement constructor: " . $e->getMessage());
            self::$logger->error("EXCEPTION stack-trace:\n" . $e->getTraceAsString());
            throw $e;
      }

      // if Folder, must be empty
      $childrenIds = $this->getChildrenIds();
      if ($this->isFolder() && (0 != count($childrenIds))) {
         $e = new Exception("delete: Folder $id must have no Children. (found ".implode(',', $childrenIds).")");
         self::$logger->error("EXCEPTION: " . $e->getMessage());
         self::$logger->error("EXCEPTION stack-trace:\n" . $e->getTraceAsString());
         throw $e;
      }

      // delete
      $query = "DELETE FROM `codev_wbselement_table` WHERE `id` = " . $this->id;
      $result = SqlWrapper::getInstance()->sql_query($query);
      if (!$result) {
         echo "<span style='color:red'>ERROR: Query FAILED</span>";
         exit;
      }
   }

   public function update() {

      $query = "UPDATE `codev_wbselement_table` SET ".
              "`title` = '" . $this->title . "'" .
              ", `parent_id` = " . (($this->parentId == null) ? "NULL" : $this->parentId) .
              ", `order` = " . $this->order .
              " WHERE `id` = " . $this->id;

      $result = SqlWrapper::getInstance()->sql_query($query);
      if (!$result) {
         echo "<span style='color:red'>ERROR: Query FAILED</span>";
         exit;
      }
   }

   public function getId() {
      return $this->id;
   }

   public function getTitle() {
      return $this->title;
   }

   public function setTitle($title) {
      $this->title = $title;
		$isModified = true;
   }

   public function getIcon() {
      return $this->icon;
   }

   public function setIcon($icon) {
      $this->icon = $icon;
		$isModified = true;
   }

   public function getFont() {
      return $this->font;
   }

   public function setFont($font) {
      $this->font = $font;
		$isModified = true;
   }

   public function getColor() {
      return $this->color;
   }

   public function setColor($color) {
      $this->color = $color;
   }

   public function getBugId() {
      return $this->bugId;
   }

   public function setBugId($bugId) {
      $this->bugId = $bugId;
   }

   public function getRootId() {
		// Note: no setter

		// if root_id is NULL, then I am the root !
      return (is_null($this->rootId)) ? $this->id : $this->rootId;
   }

   public function getParentId() {
      return $this->parentId;
   }

   public function setParentId($parentId) {
      $this->parentId = $parentId;
		$isModified = true;
   }

   public function getOrder() {
      return $this->order;
   }

   public function setOrder($order) {
      $this->order = $order;
		$isModified = true;
   }

   public function isFolder() {
      return is_null($this->bugId);
   }

	/**
	 *
	 * @param boolean $hasDetail if true, add [Progress, EffortEstim, Elapsed, Backlog, Drift]
	 * @param boolean $isManager
	 * @return array
	 */
   public function getDynatreeData($hasDetail = false, $isManager = false) {

      // TODO AND root_id = $this->getRootId()
      $query = "SELECT * FROM `codev_wbselement_table` WHERE `parent_id` = " . $this->getId() . " ORDER BY `order`";
      $result = SqlWrapper::getInstance()->sql_query($query);
      //file_put_contents('/tmp/loadWBS.txt', "$query \n", FILE_APPEND);
      if ($result) {

         $parentArray = array();

         while ($row = SqlWrapper::getInstance()->sql_fetch_object($result)) {
            $wbselement = new WBSElement2($row->id, $this->getRootId());
            $childArray = array();

            if ($wbselement->isFolder()) {

               $childArray['title'] = $wbselement->getTitle();
               $childArray['isFolder'] = true;
               $childArray['key'] = $wbselement->getId();
               $childArray['children'] = $wbselement->getDynatreeData($hasDetail, $isManager);
            } else {

               $issue = IssueCache::getInstance()->getIssue($wbselement->getBugId());
               if ($issue) {
                  $detail = '';
                  if ($hasDetail) {
							// TODO if isManager...
                     $detail = (($issue->getProgress() != null) ? ('~' . $issue->getProgress()) : '')
                             . (($issue->getMgrEffortEstim() != null) ? ('~' . $issue->getMgrEffortEstim()) : '')
                             . (($issue->getElapsed() != null) ? ('~' . $issue->getElapsed()) : '')
                             . (($issue->getBacklog() != null) ? ('~' . $issue->getBacklog()) : '')
                             . (($issue->getDriftMgr() != null) ? ('~' . $issue->getDriftMgr()) : '');
                  }

                  $childArray['title'] = $issue->getSummary() . $detail;
                  $childArray['isFolder'] = false;
                  $childArray['key'] = $wbselement->getBugId(); // yes, bugid !

               } else {
                  $childArray['title'] = 'ERROR';
                  $childArray['isFolder'] = false;
                  self::$logger->error("The issue does not exist!");
               }
            }
            if (sizeof($childArray) > 0)
               array_push($parentArray, $childArray);
         }

         // root element not only has children !
         if ($this->id === $this->getRootId()) {
               $rootArray = array(
                  'title'    => $this->getTitle(),
                  'isFolder' => true,
                  'key'      => $this->getId(),
                  'children' => $parentArray
                   );
            return $rootArray;
         } else {
            return $parentArray;
         }
      } else {
         self::$logger->error("Query failed!");
      }
   }

	/**
	 * @param type $id
	 * @return boolean
	 */
   public static function exists($id) {
      if (NULL == $id) {
         self::$logger->warn("exists(): id == NULL.");
         return FALSE;
      }

      if (NULL == self::$existsCache) { self::$existsCache = array(); }

      if (!array_key_exists($id,self::$existsCache)) {
         $query  = "SELECT COUNT(id), bug_id FROM `codev_wbselement_table` WHERE id=$id ";
         $result = SqlWrapper::getInstance()->sql_query($query);
         if (!$result) {
            echo "<span style='color:red'>ERROR: Query FAILED</span>";
            exit;
         }
         #$found  = (0 != SqlWrapper::getInstance()->sql_num_rows($result)) ? true : false;
         $nbTuples  = (0 != SqlWrapper::getInstance()->sql_num_rows($result)) ? SqlWrapper::getInstance()->sql_result($result, 0) : 0;
         self::$existsCache[$id] = (0 != $nbTuples);
      }
      return self::$existsCache[$id];
   }

	/**
	 *
	 * @param type $dynatreeDict
	 */
	public static function updateFromDynatree($dynatreeDict, $root_id = NULL, $parent_id = NULL, $order = 1) {

      file_put_contents('/tmp/tree.txt', "=============\n", FILE_APPEND);
      file_put_contents('/tmp/tree.txt', "root $root_id, parent $parent_id, order $order \n", FILE_APPEND);
      $aa = var_export($dynatreeDict, true);
      file_put_contents('/tmp/tree.txt', "aa=".$aa."\n", FILE_APPEND);
      if (self::$logger->isDebugEnabled()) {
         self::$logger->debug("updateFromDynatree(root=$root_id, parent=$parent_id, order=$order) : \n$aa");
         //self::$logger->debug($aa);
      }

		$id = NULL;
		$title = $dynatreeDict['title'];
		$icon = $dynatreeDict['icon'];
		$font = $dynatreeDict['font'];
		$color = $dynatreeDict['color'];

		$isFolder = $dynatreeDict['isFolder'];
		if ($isFolder) {
			$id = $dynatreeDict['key']; // (null if new folder)

         // new created folders have an id starting with '_'
         if (substr($id, 0, 1) === '_') {
            file_put_contents('/tmp/tree.txt', "is new Folder !\n", FILE_APPEND);
            $id = NULL;
         }

			$bug_id = NULL;
         file_put_contents('/tmp/tree.txt', "isFolder, id = $id\n", FILE_APPEND);
		} else {
			$bug_id = $dynatreeDict['key'];

			// find $id (if exists)
			// Note: parent_id may have changed (if issue moved)
			// Note: $root_id cannot be null because a WBS always starts with a folder (created at Command init)
			$query  = "SELECT id FROM `codev_wbselement_table` WHERE bug_id = $bug_id AND root_id = $root_id";
         $result = SqlWrapper::getInstance()->sql_query($query);
         if (!$result) {
            echo "<span style='color:red'>ERROR: Query FAILED</span>";
            exit;
         }
         $row = SqlWrapper::getInstance()->sql_fetch_object($result);
			if (!is_null($row)) {
				$id = $row->id;
			}
         file_put_contents('/tmp/tree.txt', "Issue id = $id, bug_id = $bug_id, \n", FILE_APPEND);
		}

		// create Element
		$wbse = new WBSElement2($id, $root_id, $bug_id, $parent_id, $order, $title, $icon, $font, $color);

		// create children
		$children = $dynatreeDict['children'];
		$childOrder = 1;
		foreach($children as $childDict) {
			self::updateFromDynatree(get_object_vars($childDict), $root_id, $wbse->getId(), $childOrder);
         $childOrder += 1;
		}
	}
}

WBSElement2::staticInit();
?>