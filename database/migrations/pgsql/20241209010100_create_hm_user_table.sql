DROP TABLE IF EXISTS hm_user;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.tables 
                   WHERE table_name = 'hm_user') THEN
        CREATE TABLE hm_user (
            username VARCHAR(255) PRIMARY KEY, 
            hash VARCHAR(255)
        );
    END IF;
END $$;
