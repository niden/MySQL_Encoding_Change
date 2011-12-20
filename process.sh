#!/bin/bash

DBUSER=root
DBPASS=1234
DBHOST=localhost
SOURCE="/home/ndimopoulos/host.sql"
LOG="/home/ndimopoulos/conversion.log"

while read line
do
    TIMENOW=`date +%Y-%m-%d-%H-%M`
    echo START $TIMENOW $line
    echo START $TIMENOW $line >> $LOG
    /usr/bin/time -f "%E real,%U user,%S sys" -v -o $LOG -a mysql -h$DBHOST -u$DBUSER -p$DBPASS -e "$line"
    
    TIMENOW=`date +%Y-%m-%d-%H-%M`
    echo END $TIMENOW 
    echo END $TIMENOW >> $LOG

done < $SOURCE

exit 0


