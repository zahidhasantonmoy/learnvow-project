<?php

// Builder A: auth strings. All auth/profile UI text lives here — no hardcoded
// Bengali/English strings in Blade (spec §0 rule 3).

return [
    // Page titles / descriptions (also used for $seo).
    'login_title' => 'লগইন',
    'login_description' => 'ফোন নম্বর ও পাসওয়ার্ড দিয়ে LearnVow-এ লগইন করুন।',
    'register_title' => 'রেজিস্টার',
    'register_description' => 'LearnVow-এ নতুন অ্যাকাউন্ট খুলুন।',
    'otp_title' => 'OTP যাচাই',
    'otp_description' => 'আপনার ফোনে পাঠানো ৬ সংখ্যার OTP দিন।',
    'forgot_title' => 'পাসওয়ার্ড ভুলে গেছেন',
    'forgot_description' => 'ফোন নম্বর দিন — OTP পাঠানো হবে।',
    'reset_title' => 'নতুন পাসওয়ার্ড',
    'reset_description' => 'OTP দিয়ে নতুন পাসওয়ার্ড সেট করুন।',

    // Form labels.
    'name' => 'নাম',
    'phone' => 'ফোন নম্বর',
    'phone_placeholder' => '01XXXXXXXXX',
    'email' => 'ইমেইল (ঐচ্ছিক)',
    'password' => 'পাসওয়ার্ড',
    'password_confirmation' => 'পাসওয়ার্ড আবার দিন',
    'remember_me' => 'মনে রাখুন',
    'otp_code' => 'OTP কোড',
    'new_password' => 'নতুন পাসওয়ার্ড',
    'current_password' => 'বর্তমান পাসওয়ার্ড',

    // Buttons / links.
    'login_button' => 'লগইন',
    'register_button' => 'অ্যাকাউন্ট খুলুন',
    'verify_button' => 'যাচাই করুন',
    'send_otp_button' => 'OTP পাঠান',
    'resend_otp' => 'আবার OTP পাঠান',
    'reset_button' => 'পাসওয়ার্ড বদলান',
    'forgot_password' => 'পাসওয়ার্ড ভুলে গেছেন?',
    'no_account' => 'অ্যাকাউন্ট নেই?',
    'have_account' => 'অ্যাকাউন্ট আছে?',
    'register_link' => 'রেজিস্টার করুন',
    'login_link' => 'লগইন করুন',
    'back_to_login' => 'লগইনে ফিরুন',

    // Messages.
    'login_failed' => 'ফোন নম্বর বা পাসওয়ার্ড ভুল।',
    'too_many_attempts' => 'অনেকবার ভুল চেষ্টা হয়েছে। :seconds সেকেন্ড পরে আবার চেষ্টা করুন।',
    'otp_sent' => 'OTP পাঠানো হয়েছে।',
    'otp_invalid' => 'OTP কোডটি ভুল। আবার চেষ্টা করুন।',
    'otp_hint' => 'ডেভ মোডে যেকোনো ৬ সংখ্যার কোড চলবে।',
    'password_reset' => 'পাসওয়ার্ড বদলে গেছে। নতুন পাসওয়ার্ড দিয়ে লগইন করুন।',
    'registered' => 'অ্যাকাউন্ট তৈরি হয়েছে।',

    // Profile.
    'profile_title' => 'প্রোফাইল',
    'profile_description' => 'আপনার প্রোফাইল ও সেটিংস।',
    'edit_profile_title' => 'প্রোফাইল সম্পাদনা',
    'privacy_title' => 'গোপনীয়তা ও আমার ডেটা',
    'delete_title' => 'অ্যাকাউন্ট মুছুন',
    'locale' => 'ভাষা',
    'country' => 'দেশ',
    'status' => 'অবস্থা',
    'roles' => 'ভূমিকা',
    'phone_verified' => 'ফোন যাচাই হয়েছে',
    'phone_not_verified' => 'ফোন যাচাই হয়নি',
    'member_since' => 'সদস্য হয়েছে',
    'save_changes' => 'পরিবর্তন সংরক্ষণ করুন',
    'edit_profile' => 'প্রোফাইল সম্পাদনা',
    'privacy_choices' => 'গোপনীয়তা পছন্দ',
    'delete_account' => 'অ্যাকাউন্ট মুছুন',
    'delete_warning' => 'সতর্কতা: অ্যাকাউন্ট মুছলে আপনার লাইব্রেরি, উইশলিস্ট ও সব ডেটা চিরতরে মুছে যাবে। এই কাজটি ফেরানো যাবে না।',
    'delete_confirm' => 'মুছে ফেলতে আপনার পাসওয়ার্ড দিন।',
    'delete_button' => 'অ্যাকাউন্ট চিরতরে মুছুন',
    'delete_super_admin_refused' => 'Super Admin অ্যাকাউন্ট মোছা যাবে না।',
    'profile_updated' => 'প্রোফাইল আপডেট হয়েছে।',
    'account_deleted' => 'আপনার অ্যাকাউন্ট মুছে ফেলা হয়েছে।',
];
