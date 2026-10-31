import assert from "node:assert/strict"
import test from "node:test"
import { parseMySqlConnectionConfig } from "../src/mysql-config.ts"

test("parses a Cloud SQL Unix socket from DATABASE_URL", () => {
  assert.deepEqual(
    parseMySqlConnectionConfig(
      "mysql://idexalos:secret@localhost/idexal_os_den?socket=%2Fcloudsql%2Fexample%3Aus-central1%3Aidexalos",
    ),
    {
      host: "localhost",
      port: 3306,
      user: "idexalos",
      password: "secret",
      database: "idexal_os_den",
      socketPath: "/cloudsql/example:us-central1:idexalos",
      ssl: undefined,
    },
  )
})

test("keeps TCP database URLs unchanged", () => {
  assert.deepEqual(parseMySqlConnectionConfig("mysql://idexalos:secret@10.0.0.4:3307/idexal_os_den"), {
    host: "10.0.0.4",
    port: 3307,
    user: "idexalos",
    password: "secret",
    database: "idexal_os_den",
    ssl: undefined,
  })
})
