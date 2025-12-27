# Bolt's Journal

## 2024-05-22 - [Performance Optimization in WordPress Assets]
**Learning:** Checking `filemtime` on every request for asset versioning causes unnecessary disk I/O, especially on high-traffic sites.
**Action:** Use a static version constant for production and only check `filemtime` when `WP_DEBUG` is enabled.
