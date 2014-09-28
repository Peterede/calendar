<?php
/**
 * ownCloud - Calendar App
 *
 * @author Georg Ehrke
 * @copyright 2014 Georg Ehrke <oc.list@georgehrke.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Calendar\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Calendar\ICalendar;
use OCP\Calendar\IObject;
use OCP\Calendar\IObjectCollection;
use OCA\Calendar\Utility\ObjectUtility;
use OCP\Calendar\ObjectType;
use OCP\IDb;

use DateTime;

class ObjectMapper extends Mapper {

	/**
	 * @var ICalendar
	 */
	protected $calendar;


	/**
	 * @param IDb $db
	 * @param ICalendar $calendar
	 * @param string $tableName
	 * @throws \InvalidArgumentException
	 */
	public function __construct(IDb $db, ICalendar $calendar,
								$tableName='clndr_objcache'){
		parent::__construct($db, $tableName);
		$this->calendar = $calendar;

		if ($calendar->getId() === null) {
			throw new \InvalidArgumentException(
				'Given calendar parameter is missing Id property!'
			);
		}
	}


	/**
	 * Finds an item from user by it's uri
	 * @param string $uri
	 * @param integer $type
	 * @throws DoesNotExistException: if the item does not exist
	 * @throws MultipleObjectsReturnedException
	 * @return IObject
	 */
	public function find($uri, $type=ObjectType::ALL){
		$sql  = 'SELECT * FROM `' . $this->getTableName() . '` ';
		$sql .= 'WHERE `uri` = ? AND `calendarid` = ?';
		$params = [$uri, $this->calendar->getId()];

		$this->addTypeQuery($type, $sql, $params);

		return $this->findEntity($sql, $params);
	}


	/**
	 * Finds all Items of calendar $calendarId
	 * @param integer $type
	 * @param integer $limit
	 * @param integer $offset
	 * @return IObjectCollection
	 */
	public function findAll($type=ObjectType::ALL, $limit=null, $offset=null){
		$sql  = 'SELECT * FROM `' . $this->getTableName() . '` ';
		$sql .= 'WHERE `calendarid` = ?';
		$params = [$this->calendar->getId()];

		$this->addTypeQuery($type, $sql, $params);

		return $this->findEntities($sql, $params, $limit, $offset);
	}


	/**
	 * Finds all Items of calendar $calendarId
	 * @param integer $type
	 * @return array
	 */
	public function listAll($type=ObjectType::ALL){
		$sql  = 'SELECT `objecturi` FROM `' . $this->getTableName() . '` ';
		$sql .= 'WHERE `calendarid` = ?';
		$params = [$this->calendar->getId()];

		$this->addTypeQuery($type, $sql, $params);

		return $this->findEntities($sql, $params);
	}


	/**
	 * Finds all Items of calendar $calendarId from $start to $end
	 * @param DateTime $start
	 * @param DateTime $end
	 * @param integer $type
	 * @param integer $limit
	 * @param integer $offset
	 * @return IObjectCollection
	 */
	public function findAllInPeriod(DateTime $start, DateTime $end,
									$type=ObjectType::ALL,
									$limit=null, $offset=null) {
		$sql  = 'SELECT * FROM `' . $this->getTableName() . '` ';
		$sql .= 'WHERE `calendarid` = ?';
		$params = [$this->calendar->getId()];

		$this->addTypeQuery($type, $sql, $params);
		$this->addPeriodQuery($start, $end, $sql, $params);

		return $this->findEntities($sql, $params, $limit, $offset);
	}


	/**
	 * number of objects in a calendar
	 * @throws DoesNotExistException: if the item does not exist
	 * @return integer
	 */
	public function count(){
		$sql  = 'SELECT COUNT(*) AS `count` ';
		$sql .= 'FROM `' . $this->getTableName() . '` ';
		$sql .= 'WHERE `calendarid` = ?';
		$params = [$this->calendar->getId()];

		$row = $this->findOneQuery($sql, $params);
		return intval($row['count']);
	}


	/**
	 * delete multiple objects based on their objectUris
	 * @param array $objectUris
	 */
	public function deleteList(array $objectUris=[]) {
		if (empty($objectUris) === true) {
			return;
		}

		$sql  = 'DELETE FROM `' . $this->getTableName() . '` ';
		$sql .= 'WHERE `calendarid` = ? AND (';
		$params = [$this->calendar->getId()];

		$sqlElements = [];
		foreach($objectUris as $objectUri) {
			$sqlElements[] = '`objecturi` = ?';
			$params[] = $objectUri;
		}

		if (count($sqlElements) === 0) {
			return;
		}

		$sql .= implode(' OR ', $sqlElements);
		$sql .= ')';

		$this->execute($sql, $params);
	}


	/**
	 * Creates an entity from a row. Automatically determines the entity class
	 * from the current mapper name (MyEntityMapper -> MyEntity)
	 * @param array $row the row which should be converted to an entity
	 * @return Entity the entity
	 */
	protected function mapRowToEntity($row) {
		return parent::mapRowToEntity($row);
	}


	/**
	 * get UTC from a datetime object
	 * @param DateTime $datetime
	 * @return string
	 */
	private function getUTC(DateTime $datetime){
		return ObjectUtility::getUTCforMDB($datetime);
	}


	/**
	 * @param integer $type
	 * @param string &$sql
	 * @param array &$params
	 * @return void
	 */
	private function addTypeQuery($type, &$sql, &$params) {
		$sqlElements = array();

		if (ObjectType::EVENT & $type) {
			$sqlElements[] = '`type` = ?';
			$params[] = ObjectType::EVENT;
		}
		if (ObjectType::JOURNAL & $type) {
			$sqlElements[] = '`type` = ?';
			$params[] = ObjectType::JOURNAL;
		}
		if (ObjectType::TODO & $type) {
			$sqlElements[] = '`type` = ?';
			$params[] = ObjectType::TODO;
		}

		if (count($sqlElements) === 0) {
			return;
		}

		$sql .= ' AND (';
		$sql .= implode(' OR ', $sqlElements);
		$sql .= ')';
	}


	/**
	 * @param DateTime $start
	 * @param DateTime $end
	 * @param string &$sql
	 * @param array &$params
	 */
	private function addPeriodQuery(DateTime $start, DateTime $end, &$sql, &$params) {
		$sql .= 'AND ((`startdate` >= ? AND `startdate` <= ?) ';
		$sql .= 'OR (`enddate` >= ? AND `enddate` <= ?) ';
		$sql .= 'OR (`startdate` <= ? AND `enddate` >= ?) ';
		$sql .= 'OR (`lastoccurence` >= ? AND `startdate` <= ? AND `repeating` = 1)) ';
		$sql .= 'ORDER BY `repeating`';

		$utcStart = $this->getUTC($start);
		$utcEnd = $this->getUTC($end);

		$params = array_merge($params, array(
			$utcStart,
			$utcEnd,
			$utcStart,
			$utcEnd,
			$utcStart,
			$utcEnd,
			$utcStart,
			$utcEnd
		));
	}
}