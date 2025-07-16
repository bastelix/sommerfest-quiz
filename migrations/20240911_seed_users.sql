-- Seed default users for all roles
INSERT INTO users (username, password, role) VALUES
    ('admin', '$2y$12$B8GYqPQQK3F80qJeu.vRXeskUmaET17P91MmApvIahLX8qWqdC/JW', 'admin'),
    ('catalog-editor', '$2y$12$cqeheVnVT8rp7bR3dU6VxuB21tOg4mqWnYJV4HnSkraiQQWpug/fi', 'catalog-editor'),
    ('event-manager', '$2y$12$CXo0lxynUmIL7YvhkcS39uh7tqzgzzlXELGj0sTO1q3TOMPQXGwsy', 'event-manager'),
    ('analyst', '$2y$12$gsSk0qYxpQStGmhK4WQB6OHS82taT778avI3ge7K2xoro1uvGH.gO', 'analyst'),
    ('team-manager', '$2y$12$oi.gl3hmXZnbiMIQTzcPy.RqurPkl.I0LSJWsfHp.8yWfglp.1z3y', 'team-manager'),
    ('service-account', '$2y$12$MoevWkEJWoWym8ZFtvNWU.p37EX5DqxlOXEdeeU1SFscnRZZLEC6G', 'service-account')
ON CONFLICT (username) DO NOTHING;
