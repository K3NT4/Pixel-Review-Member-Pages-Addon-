from playwright.sync_api import Page, expect, sync_playwright
import os

def verify_admin_settings_ui(page: Page):
    # Load the mock HTML file
    cwd = os.getcwd()
    file_url = f"file://{cwd}/verification/mock_admin_settings.html"
    page.goto(file_url)

    # 1. Initial State: All options hidden
    expect(page.locator("#prmp_captcha_turnstile_opts")).not_to_be_visible()
    expect(page.locator("#prmp_captcha_recaptcha_opts")).not_to_be_visible()
    expect(page.locator("#prmp_rate_limit_opts")).not_to_be_visible()
    expect(page.locator("#prmp_sl_google_opts")).not_to_be_visible()
    expect(page.locator("#prmp_sl_wp_opts")).not_to_be_visible()

    page.screenshot(path="verification/admin_settings_initial.png")

    # 2. Toggle Rate Limit
    page.locator("#prmp_rate_limit_check").check()
    expect(page.locator("#prmp_rate_limit_opts")).to_be_visible()
    page.screenshot(path="verification/admin_settings_ratelimit.png")

    # 3. Toggle Google Social Login
    page.locator("#prmp_sl_google_check").check()
    expect(page.locator("#prmp_sl_google_opts")).to_be_visible()

    # 4. Toggle WordPress Social Login
    page.locator("#prmp_sl_wp_check").check()
    expect(page.locator("#prmp_sl_wp_opts")).to_be_visible()

    # 5. Select Cloudflare Turnstile
    page.select_option("#prmp_captcha_select", "turnstile")
    expect(page.locator("#prmp_captcha_turnstile_opts")).to_be_visible()
    expect(page.locator("#prmp_captcha_recaptcha_opts")).not_to_be_visible()

    page.screenshot(path="verification/admin_settings_turnstile.png")

    # 6. Select Google reCAPTCHA
    page.select_option("#prmp_captcha_select", "recaptcha_v3")
    expect(page.locator("#prmp_captcha_turnstile_opts")).not_to_be_visible()
    expect(page.locator("#prmp_captcha_recaptcha_opts")).to_be_visible()

    page.screenshot(path="verification/admin_settings_recaptcha.png")

    # 7. Final State: All toggles active + Recaptcha
    page.screenshot(path="verification/admin_settings_final.png")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            verify_admin_settings_ui(page)
            print("Verification script completed successfully.")
        except Exception as e:
            print(f"Verification script failed: {e}")
            page.screenshot(path="verification/failure.png")
            raise e
        finally:
            browser.close()
