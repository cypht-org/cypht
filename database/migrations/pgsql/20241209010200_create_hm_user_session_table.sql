DROP TABLE IF EXISTS hm_user_session;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.tables 
                   WHERE table_name = 'hm_user_session') THEN
        CREATE TABLE hm_user_session (
            hm_id VARCHAR(255) PRIMARY KEY, 
            data BYTEA, 
            date TIMESTAMP
        );
    END IF;
END $$;
