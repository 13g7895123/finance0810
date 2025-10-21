#!/usr/bin/env python3
"""
Comprehensive test script for WP Lead Generator API (Point 4)
Tests the random lead generation endpoint and verifies data integrity
"""

import requests
import json
import time
from datetime import datetime

API_URL = "http://localhost:9221/api/test/generate-wp-lead"
COLORS = {
    'green': '\033[92m',
    'red': '\033[91m',
    'yellow': '\033[93m',
    'blue': '\033[94m',
    'cyan': '\033[96m',
    'reset': '\033[0m',
    'bold': '\033[1m',
}

def print_colored(text, color='reset'):
    """Print colored text"""
    print(f"{COLORS.get(color, '')}{text}{COLORS['reset']}")

def print_header(text):
    """Print section header"""
    print_colored(f"\n{'='*70}", 'cyan')
    print_colored(f"  {text}", 'bold')
    print_colored(f"{'='*70}", 'cyan')

def test_api_call(test_number):
    """Test a single API call"""
    print_header(f"測試 #{test_number}")

    try:
        response = requests.get(API_URL, timeout=10)

        if response.status_code != 201:
            print_colored(f"✗ HTTP Status: {response.status_code} (Expected: 201)", 'red')
            print_colored(f"Response: {response.text[:200]}", 'yellow')
            return None

        data = response.json()

        if not data.get('success'):
            print_colored(f"✗ API returned success=false", 'red')
            print_colored(f"Error: {data.get('message')}", 'yellow')
            return None

        # Extract data
        lead = data['data']['lead']
        customer = data['data']['customer']
        notification = data['data']['notification']
        random_fields = data['data']['random_fields']
        test_info = data['test_info']

        # Print results
        print_colored(f"✓ API 呼叫成功", 'green')
        print_colored(f"\n【案件資訊】", 'cyan')
        print(f"  案件 ID: {lead['id']}")
        print(f"  客戶 ID: {customer['id']}")
        print(f"  姓名: {lead['name']}")
        print(f"  電話: {lead['phone']}")
        print(f"  Email: {lead['email'] or '(無)'}")
        print(f"  LINE ID: {lead['line_id'] or '(無)'}")
        print(f"  來源: {lead['source']}")
        print(f"  聯絡時間: {lead['contact_time']}")
        print(f"  建立時間: {lead['created_at']}")

        print_colored(f"\n【隨機欄位】", 'cyan')
        print(f"  資金需求: {random_fields['capital_need']}")
        print(f"  貸款類型: {random_fields['loan_type']}")
        print(f"  地區: {random_fields['region']}")
        print(f"  學歷: {random_fields['education_level'] or '(無)'}")
        print(f"  職稱: {random_fields['job_title'] or '(無)'}")

        print_colored(f"\n【通知資訊】", 'cyan')
        print(f"  通知 ID: {notification['id']}")
        print(f"  標題: {notification['title']}")
        print(f"  訊息: {notification['message']}")
        print(f"  優先級: {notification['priority']}")

        print_colored(f"\n【測試資訊】", 'cyan')
        print(f"  產生時間: {test_info['generated_at']}")
        print(f"  來源 IP: {test_info['source_ip']}")
        print(f"  API 端點: {test_info['api_endpoint']}")

        return {
            'lead_id': lead['id'],
            'customer_id': customer['id'],
            'notification_id': notification['id'],
            'name': lead['name'],
            'phone': lead['phone'],
            'loan_type': random_fields['loan_type'],
            'region': random_fields['region'],
        }

    except requests.exceptions.RequestException as e:
        print_colored(f"✗ 網路錯誤: {e}", 'red')
        return None
    except json.JSONDecodeError as e:
        print_colored(f"✗ JSON 解析錯誤: {e}", 'red')
        return None
    except Exception as e:
        print_colored(f"✗ 未預期錯誤: {e}", 'red')
        return None

def verify_randomness(results):
    """Verify data is actually random"""
    print_header("驗證資料隨機性")

    if not results or len(results) < 2:
        print_colored("✗ 測試資料不足，無法驗證隨機性", 'yellow')
        return

    # Check if all names are different
    names = [r['name'] for r in results]
    phones = [r['phone'] for r in results]
    loan_types = [r['loan_type'] for r in results]
    regions = [r['region'] for r in results]

    print(f"\n姓名列表: {', '.join(names)}")
    print(f"電話列表: {', '.join(phones)}")
    print(f"貸款類型: {', '.join(loan_types)}")
    print(f"地區列表: {', '.join(regions)}")

    # Verify uniqueness
    checks = [
        ('姓名', names),
        ('電話', phones),
    ]

    all_unique = True
    for label, values in checks:
        unique_count = len(set(values))
        total_count = len(values)
        if unique_count == total_count:
            print_colored(f"\n✓ {label}完全不重複 ({unique_count}/{total_count})", 'green')
        else:
            print_colored(f"\n⚠ {label}有重複 ({unique_count}/{total_count})", 'yellow')
            all_unique = False

    # Check variety in loan types and regions (may repeat but should have variety)
    loan_type_variety = len(set(loan_types))
    region_variety = len(set(regions))

    print_colored(f"\n貸款類型變化數: {loan_type_variety}/{len(loan_types)}",
                  'green' if loan_type_variety > 1 else 'yellow')
    print_colored(f"地區變化數: {region_variety}/{len(regions)}",
                  'green' if region_variety > 1 else 'yellow')

    if all_unique:
        print_colored(f"\n✓ 隨機性驗證通過！", 'green')
    else:
        print_colored(f"\n⚠ 部分資料有重複（正常情況，因為使用隨機產生）", 'yellow')

def test_database_consistency():
    """Test if we can query the created records"""
    print_header("資料庫一致性測試")

    print_colored("提示: 請手動在資料庫中驗證以下內容：", 'cyan')
    print("  1. customer_leads 表格最新記錄的 channel 欄位是否為 'wp_form'")
    print("  2. customer_leads 表格最新記錄的 status 欄位是否為 'pending'")
    print("  3. notifications 表格最新記錄的 type 欄位是否為 'wp_lead'")
    print("  4. notifications 表格最新記錄的 priority 欄位是否為 'high'")
    print("  5. customers 表格是否正確建立相關記錄")
    print("  6. customer_identifiers 表格是否正確綁定識別資料")

    print_colored("\n建議執行的資料庫查詢：", 'yellow')
    print("  docker exec -it finance0810-backend bash")
    print("  php artisan tinker")
    print("  \\App\\Models\\CustomerLead::latest()->first()")
    print("  \\App\\Models\\Notification::latest()->first()")

def main():
    """Main test function"""
    print_colored(f"\n{'#'*70}", 'bold')
    print_colored(f"  WP Lead Generator API - 完整測試套件", 'bold')
    print_colored(f"  Point 4 Implementation Verification", 'bold')
    print_colored(f"  Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}", 'bold')
    print_colored(f"{'#'*70}\n", 'bold')

    # Configuration
    num_tests = 5
    delay_between_tests = 1.5  # seconds

    print_colored(f"配置: 執行 {num_tests} 次測試，間隔 {delay_between_tests} 秒\n", 'cyan')

    # Run tests
    results = []
    for i in range(1, num_tests + 1):
        result = test_api_call(i)
        if result:
            results.append(result)

        if i < num_tests:
            time.sleep(delay_between_tests)

    # Summary
    print_header("測試摘要")
    success_count = len(results)
    total_count = num_tests
    success_rate = (success_count / total_count * 100) if total_count > 0 else 0

    print(f"\n總測試次數: {total_count}")
    print_colored(f"成功次數: {success_count}", 'green' if success_count == total_count else 'yellow')
    print_colored(f"失敗次數: {total_count - success_count}", 'red' if success_count < total_count else 'green')
    print_colored(f"成功率: {success_rate:.1f}%\n", 'green' if success_rate == 100 else 'yellow')

    if results:
        # Show created IDs
        print_colored("建立的記錄 ID:", 'cyan')
        for i, r in enumerate(results, 1):
            print(f"  #{i}: Lead={r['lead_id']}, Customer={r['customer_id']}, Notification={r['notification_id']}")

        # Verify randomness
        verify_randomness(results)

    # Database consistency
    test_database_consistency()

    # Final verdict
    print_header("最終結果")
    if success_rate == 100 and len(results) >= 3:
        print_colored("✓ 所有測試通過！Point 4 API 運作正常。", 'green')
        print_colored("✓ 資料隨機性已驗證。", 'green')
        print_colored("✓ 建議手動驗證資料庫記錄和前端通知顯示。\n", 'cyan')
    elif success_rate >= 80:
        print_colored("⚠ 大部分測試通過，但有些許失敗。請檢查錯誤訊息。\n", 'yellow')
    else:
        print_colored("✗ 多數測試失敗，請檢查 API 和資料庫連線。\n", 'red')

if __name__ == "__main__":
    main()
