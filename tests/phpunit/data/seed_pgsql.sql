-- Test Data Seed for PHPUnit Tests (PostgreSQL)
-- Password for 'unittestuser' is 'unittestpass'
-- Password for 'testuser' is hashed with argon2id

INSERT INTO hm_user (username, hash) VALUES 
('unittestuser', 'sha512:86000:xfEgf7NIUQ2XkeU5tnIcA+HsN8pUllMVdzpJxCSwmbsZAE8Hze3Zs+MeIqepwocYteJ92vhq7pjOfrVThg/p1voELkDdPenU8i2PgG9UTI0IJTGhMN7rsUILgT6XlMAKLp/u2OD13sukUFcQNTdZNFqMsuTVTYw/Me2tAnFwgO4=:rfyUhYsWBCknx6EmbeswN0fy0hAC0N3puXzwWyDRquA='),
('testuser', '$argon2id$v=19$m=65536,t=2,p=1$dw4pTU24zRKHCEkLcloU/A$9NJm6ALQhVpB2HTHmVHjOai912VhURUDAPsut5lrEa0')
ON CONFLICT (username) DO UPDATE SET hash = EXCLUDED.hash;

INSERT INTO hm_user_settings (username, settings) VALUES 
('testuser', 'sFpVPU/hPvmfeiEKUBs4w1EizmbW/Ze2BALZf6kdJrIU3KVZrsqIhKaWTNNFRm3p51ssRAH2mpbxBMhsdpOAqIZMXFHjLttRu9t5WZWOkN7qwEh2LRq6imbkMkfqXg//K294QDLyWjE0Lsc/HSGqnguBF0YUVLVmWmdeqq7/OrXUo4HNbU88i4s2gkukKobJA2hjcOEq/rLOXr3t4LnLlcISnUbt4ptalSbeRrOnx4ehZV8hweQf1E+ID7s/a+8HHx1Qo713JDzReoLEKUsxRQ==')
ON CONFLICT (username) DO UPDATE SET settings = EXCLUDED.settings;
