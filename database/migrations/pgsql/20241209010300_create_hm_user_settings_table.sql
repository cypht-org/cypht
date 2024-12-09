DROP TABLE IF EXISTS hm_user_settings;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.tables 
                   WHERE table_name = 'hm_user_settings') THEN
        CREATE TABLE hm_user_settings (
            username VARCHAR(255) PRIMARY KEY, 
            settings BYTEA
        );
    END IF;
END $$;
