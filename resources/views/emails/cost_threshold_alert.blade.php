@component('mail::message')
# @if($isTest) [TEST ALERT] @endif @if($percentage >= 100) 🚨 Budget Threshold Exceeded @elseif($percentage >= 90) ⚠️ Critical Budget Threshold ({{ $percentage }}%) @else 📊 Budget Threshold Notice ({{ $percentage }}%) @endif

Hello,

This is an automated notification from **{{ config('app.name') }}** regarding your monthly WhatsApp messaging expenditure.

The threshold rule **"{{ $ruleName }}"** has reached **{{ $percentage }}%** of its configured monthly limit for **{{ $monthName }}**.

---

### 💳 Budget Breakdown:
- **Configured Monthly Budget:** **${{ number_format($budgetAmount, 2) }} USD**
- **Current Spend this Month:** **${{ number_format($currentSpend, 2) }} USD** ({{ number_format(($currentSpend / max(1, $budgetAmount)) * 100, 1) }}%)
- **Remaining Budget:** **${{ number_format(max(0, $budgetAmount - $currentSpend), 2) }} USD**
@if(!empty($bankNames))
- **Applicable Banks:** {{ implode(', ', $bankNames) }}
@else
- **Applicable Banks:** All Accessible Banks
@endif

---

@if($percentage >= 100)
> **🚨 Warning:** Your spending has reached or exceeded 100% of the allocated budget. Further outbound campaigns will incur charges beyond this budget limit.
@elseif($percentage >= 90)
> **⚠️ Notice:** Spending is approaching the maximum budget threshold for this calendar month.
@else
> **ℹ️ Information:** Spending is tracking steadily against the configured budget milestone.
@endif

@component('mail::button', ['url' => url('/meta-billing')])
View Meta Billing & Usage
@endcomponent

Regards,<br>
{{ config('app.name') }} Automated Billing Guard
@endcomponent
