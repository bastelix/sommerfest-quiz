CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;

DO $$
DECLARE
    new_pass TEXT;
BEGIN
    IF NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin') THEN
        new_pass := encode(public.gen_random_bytes(16), 'hex');
        INSERT INTO users (username, password, role, active)
        VALUES (
            'admin',
            public.crypt(new_pass, public.gen_salt('bf')),
            'admin',
            TRUE
        );
        RAISE NOTICE 'Created admin user with password: %', new_pass;
    END IF;
END $$ LANGUAGE plpgsql;
