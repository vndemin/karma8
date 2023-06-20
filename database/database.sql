DROP TABLE IF EXISTS users;
CREATE TABLE users
(
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    validts TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    confirmed BOOLEAN NOT NULL DEFAULT false,
    valid BOOLEAN DEFAULT NULL
);

CREATE UNIQUE INDEX unique_users_email_index ON users (email);
CREATE INDEX users_validts_not_null_idx ON users USING btree (validts) WHERE validts IS NOT NULL;

INSERT INTO users (username, email, validts, confirmed, valid)
    SELECT
        md5(random()::text) AS username,
        md5(random()::text) || '@example.com' AS email,
        CASE WHEN random() < 0.15 THEN (now() + (random() * interval '45 days')) ELSE NULL END AS validts,
        CASE WHEN random() < 0.22 THEN true ELSE false END AS confirmed,
        null AS valid
FROM generate_series(1, 1000000);
