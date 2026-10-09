// Controlled read-only load profile; no live API, no mutating endpoints.
import http from 'k6/http';
import { check, sleep } from 'k6';

const origin = __ENV.TEST_V2_LOAD_URL;
const allow = __ENV.TEST_V2_LOAD_ALLOW_HOST;
if (__ENV.TEST_V2_APPROVAL !== 'RUN_TEST_V2' || !origin || !allow) {
  throw new Error('Load test blocked until explicitly approved');
}
const uri = new URL(origin);
if (uri.protocol !== 'https:' || uri.hostname !== allow) {
  throw new Error('Unapproved load-test hostname');
}
export const options = {
  vus: 2,
  duration: '30s',
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1000'],
  },
};
export default function () {
  const result = http.get(`${origin.replace(/\/$/, '')}/up`, { timeout: '5s', redirects: 0 });
  check(result, { 'non-server-error health response': r => r.status < 500 });
  sleep(1);
}