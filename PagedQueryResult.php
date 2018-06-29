<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace sammaye\pq;

use Yii;
use yii\base\BaseObject;

/**
 * BatchQueryResult represents a batch query from which you can retrieve data in batches.
 *
 * You usually do not instantiate BatchQueryResult directly. Instead, you obtain it by
 * calling [[Query::batch()]] or [[Query::each()]]. Because BatchQueryResult implements the [[\Iterator]] interface,
 * you can iterate it to obtain a batch of data in each iteration. For example,
 *
 * ```php
 * $query = (new Query)->from('user');
 * foreach ($query->batch() as $i => $users) {
 *     // $users represents the rows in the $i-th batch
 * }
 * foreach ($query->each() as $user) {
 * }
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class PagedQueryResult extends BaseObject implements \Iterator
{
    /**
     * @var Connection the DB connection to be used when performing batch query.
     * If null, the "db" application component will be used.
     */
    public $db;
    /**
     * @var Query the query object associated with this batch query.
     * Do not modify this property directly unless after [[reset()]] is called explicitly.
     */
    public $query;
    /**
     * @var integer the number of rows to be returned in each batch.
     */
    public $batchSize = 100;
    /**
     * @var boolean whether to return a single row during each iteration.
     * If false, a whole batch of rows will be returned in each iteration.
     */
    public $each = false;
    /**
     * @var boolean whether or not to paginate the query via offset and limit
     * This is helpful for PHP7 where resuls sets actually add to a PHP's process 
     * memory usage by default
     */
    public $page = false;

    /**
     * @var DataReader the data reader associated with this batch query.
     */
    private $_dataReader;
    /**
     * @var array the data retrieved in the current batch
     */
    private $_batch;
    /**
     * @var mixed the value for the current iteration
     */
    private $_value;
    /**
     * @var string|integer the key for the current iteration
     */
    private $_key;
    /**
     * @var integer holds the value of the offset for pagination
     */
    private $_offset;
    /**
     * @var integer holds the limit of the query, if one is provided
     */
    private $_limit;


    /**
     * Destructor.
     */
    public function __destruct()
    {
        // make sure cursor is closed
        $this->reset();
    }

    /**
     * Resets the batch query.
     * This method will clean up the existing batch query so that a new batch query can be performed.
     */
    public function reset()
    {
        if ($this->_dataReader !== null) {
            $this->_dataReader->close();
        }
        $this->_dataReader = null;
        $this->_batch = null;
        $this->_value = null;
        $this->_key = null;
        $this->_offset = null;
        $this->_limit = null;
    }

    /**
     * Resets the iterator to the initial state.
     * This method is required by the interface [[\Iterator]].
     */
    public function rewind()
    {
        $this->reset();
        $this->next();
    }

    /**
     * Moves the internal pointer to the next dataset.
     * This method is required by the interface [[\Iterator]].
     */
    public function next()
    {
        if ($this->_batch === null || !$this->each || $this->each && next($this->_batch) === false) {
            $this->_batch = $this->fetchData();
            reset($this->_batch);
        }

        if ($this->each) {
            $this->_value = current($this->_batch);
            if ($this->query->indexBy !== null) {
                $this->_key = key($this->_batch);
            } elseif (key($this->_batch) !== null) {
                $this->_key++;
            } else {
                $this->_key = null;
            }
        } else {
            $this->_value = $this->_batch;
            $this->_key = $this->_key === null ? 0 : $this->_key + 1;
        }
    }

    /**
     * Fetches the next batch of data.
     * @return array the data fetched
     */
    protected function fetchData()
    {
        if($this->_batch === null && $this->query->limit !== null){
            // If it hasn't been fetched lets record the limit
            $this->_limit = $this->query->limit;
        }
        
        if($this->page){
            
            // If we are reaching near of the end of the predefined limit then
            // let's sort that out
            $batchSize = $this->batchSize;
            if($this->_limit !== null){
                if($this->_offset > $this->_limit){
                    $batchSize = 0;
                }elseif($this->_offset === $this->_limit){
                    // Normally DB techs are exclusive in OFFSET
                    $batchSize = 1;
                }elseif(($this->batchSize + $this->_offset) >= $this->_limit){
                    $batchSize = $this->_limit - $this->_offset;
                }
            }
            
            $this->_dataReader = $this->query
                ->limit($batchSize)
                ->offset($this->_offset)
                ->createCommand($this->db)
                ->query();
                
        }elseif($this->_dataReader === null){
            $this->_dataReader = $this->query->createCommand($this->db)->query();
        }

        $rows = [];
        $count = 0;
        while ($count++ < $this->batchSize && ($row = $this->_dataReader->read())) {
            $rows[] = $row;
        }
        
        if($this->page){
            // If the result has been pulled without error then increment the offset
            $this->_offset += $batchSize;
        }

        return $this->query->populate($rows);
    }

    /**
     * Returns the index of the current dataset.
     * This method is required by the interface [[\Iterator]].
     * @return integer the index of the current row.
     */
    public function key()
    {
        return $this->_key;
    }

    /**
     * Returns the current dataset.
     * This method is required by the interface [[\Iterator]].
     * @return mixed the current dataset.
     */
    public function current()
    {
        return $this->_value;
    }

    /**
     * Returns whether there is a valid dataset at the current position.
     * This method is required by the interface [[\Iterator]].
     * @return boolean whether there is a valid dataset at the current position.
     */
    public function valid()
    {
        return !empty($this->_batch);
    }
}
