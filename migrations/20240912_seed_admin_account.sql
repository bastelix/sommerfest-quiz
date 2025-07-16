-- Seed admin login after roles
INSERT INTO users (username, password, role) VALUES
    ('admin', '$2y$12$B8GYqPQQK3F80qJeu.vRXeskUmaET17P91MmApvIahLX8qWqdC/JW', 'admin')
ON CONFLICT (username) DO NOTHING;
