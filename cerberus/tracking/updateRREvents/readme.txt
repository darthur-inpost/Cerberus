Algorithm:
1. Get the current timestamp
2. Fetch the latest 'last_logged' datetime from the event_log_rr_events table. 
3. Fetch all 'consignment_no' rows from the returns table where 'updated_at' > the latest 'last_logged' datetime
4. For each 'consignment_no':
	i)Fetch the latest timestamps for the three status events from the pack_status_event table.
	ii)Check if 'consignment_no' already exists in the returns_events table.
	   	a. If so:  Update the existing row in the returns_events table
	   	b. Otherwise: Add new row in the returns_events table  
5. End 
6. Insert the current timestamp as the 'last_logged' value in the event_log_rr_events table

