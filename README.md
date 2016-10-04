# yii2-pq
A paged query class for Yii2 to deal with PHP7 changes in mysqlnd

It will load your result set the same way as pagination works using `OFFSET` and `LIMIT`.

This extension is 99% for MySQL users.

## Installation

Just include it in your composer:

    php composer require "sammaye/yii2-pq":"~1.0.0"

## Usage

	$query = (new \sammaye\pq\Query)
		->from(Title::tableName())
		->where('live=1')
		->limit(300)
		->orderBy(['id' => SORT_DESC]);
	foreach($query->each() as $k => $v){

And it will return in batches of 100 up to 300.

As you can see there is not much to learn about this extension except how to include it.

**Note:** There is no active record part to this query currently due to the 
nature of PHP class inheritance and inclusion which means I would have to copy the `ActiveQuery` entirely.

## Why?

I noticed that many of my cronjobs failed after an upgrade to PHP7. It was not 
long before I realised that there was two changes since PHP5.4:

- The default MySQL driver used by PHP7 has changed to mysqlnd
- And, mysqlnd now adds your [result sets to it's own memory](http://php.net/manual/en/mysqlinfo.concepts.buffering.php), buffering queries

Added to that, my own observations that unbuffered queries suck meant that I created this.