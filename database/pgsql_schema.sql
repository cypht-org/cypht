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

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.tables 
                   WHERE table_name = 'hm_user_session') THEN
        CREATE TABLE hm_user_session (
            hm_id VARCHAR(255) PRIMARY KEY, 
            data BYTEA, 
            date TIMESTAMP,
            hm_version INT DEFAULT 1
        );
    END IF;
END $$;

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
