DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name = 'hm_user_session' 
          AND column_name = 'hm_version'
    ) THEN
        ALTER TABLE hm_user_session 
        ADD COLUMN hm_version INT DEFAULT 1;
    END IF;
END $$;
