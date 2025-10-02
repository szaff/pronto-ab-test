# Pronto A/B Testing - Complete User Guide

Welcome to Pronto A/B Testing! This guide will help you create, manage, and optimize your A/B tests to improve conversions and user engagement on your WordPress website.

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Understanding A/B Testing](#understanding-ab-testing)
3. [Creating Your First Campaign](#creating-your-first-campaign)
4. [Adding Variations](#adding-variations)
5. [Implementing Tests on Your Site](#implementing-tests-on-your-site)
6. [Tracking & Analytics](#tracking--analytics)
7. [Declaring a Winner](#declaring-a-winner)
8. [Best Practices](#best-practices)
9. [Troubleshooting](#troubleshooting)
10. [FAQs](#faqs)

---

## Getting Started

### What is Pronto A/B Testing?

Pronto A/B Testing is a WordPress plugin that helps you test different versions of your content to see which performs better. You can test headlines, buttons, images, entire page sections, and more - all without coding.

### Key Features

- ✅ **Easy Shortcode System** - Add tests anywhere with simple shortcodes
- ✅ **Visual Editor Support** - Create variations using WordPress's familiar editor
- ✅ **Automatic Tracking** - Clicks, forms, and conversions tracked automatically
- ✅ **Privacy-First** - All data stays on your server
- ✅ **No External Services** - Works entirely within WordPress

### System Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Modern web browser (Chrome, Firefox, Safari, Edge)

---

## Understanding A/B Testing

### What is A/B Testing?

A/B testing (also called split testing) is a method of comparing two versions of a webpage or element to see which one performs better. You show version A to some visitors and version B to others, then measure which version achieves your goal more effectively.

### When Should You Use A/B Testing?

Use A/B testing when you want to:

- 📈 **Increase conversions** - More sign-ups, purchases, or downloads
- 🎯 **Improve engagement** - Better click-through rates or time on page
- 💡 **Make data-driven decisions** - Remove guesswork from design choices
- 🚀 **Optimize marketing** - Find the best messaging for your audience
- 🎨 **Test design changes** - Validate changes before full rollout

### Key Concepts

**Campaign**: The overall test you're running (e.g., "Homepage Hero Test")

**Variation**: A different version of your content being tested

**Control**: The original version (your current content)

**Impression**: When a visitor sees one of your test variations

**Conversion**: When a visitor completes your desired action (click, form submit, purchase)

**Traffic Split**: How you divide visitors between variations (e.g., 50/50)

**Statistical Significance**: Confidence that your results aren't due to chance

---

## Creating Your First Campaign

### Step 1: Access the Plugin

1. Log in to your WordPress admin dashboard
2. Navigate to **A/B Tests** in the left sidebar
3. Click **Add New** to create a new campaign

### Step 2: Name Your Campaign

Give your campaign a descriptive name that explains what you're testing:

**Good Examples**:
- "Homepage Headline Test"
- "Pricing Page CTA Button"
- "Product Description A/B Test"

**Avoid**:
- "Test 1"
- "New Test"
- "AB"

### Step 3: Add a Description (Optional)

Write a brief description of what you're testing and why:

```
Testing two different value propositions for our main headline:
- Control: Feature-focused messaging
- Variation: Benefit-focused messaging
Goal: Increase sign-up button clicks
```

### Step 4: Configure Campaign Settings

#### Target Content (Optional)

If you want the test to automatically appear on a specific page:

1. Select **Post Type** (Page, Post, Product, etc.)
2. Choose the specific **Post/Page**
3. The test will automatically inject at the top of that content

> 💡 **Tip**: Leave this blank if you want to manually place the test using a shortcode.

#### Traffic Split

Choose how to divide visitors between variations:

- **50/50** - Equal split (recommended for starting)
- **60/40** - Slightly favor one variation
- **70/30** - Conservative test, favor control
- **80/20** - Very conservative, minimal traffic to test
- **Custom** - Set individual weights per variation

#### Start & End Dates

- **Start Date**: When the test should begin (leave blank to start immediately)
- **End Date**: When the test should end (leave blank to run indefinitely)

> ⚠️ **Important**: Tests will continue collecting data until you manually stop them or the end date is reached.

### Step 5: Save Your Campaign

Click **Save Draft** to save your campaign. You can activate it later after adding variations.

---

## Adding Variations

Variations are the different versions of content you want to test. Every campaign needs at least 2 variations (including the control).

### Creating Variations

#### Method 1: Using WordPress Editor (Recommended)

1. From the campaign edit screen, click **Add New Variation**
2. You'll be taken to the WordPress editor
3. Enter a **Variation Name** (e.g., "Headline - Benefit Focused")
4. Create your content using the WordPress editor:
   - Add text, images, buttons
   - Use any WordPress blocks
   - Format with the visual editor
   - Add shortcodes from other plugins

5. In the **Variation Details** sidebar:
   - Select the **Campaign** this belongs to
   - Check **Control Variation** if this is your original content
   - Set the **Traffic Weight** percentage

6. Click **Publish**

#### Method 2: Quick Add from Campaign Screen

1. In the campaign editor, find the **Variations** section
2. Click **Add Variation**
3. Enter the variation name and content directly
4. Set the control flag and weight
5. Save

### Variation Best Practices

#### 1. Always Have a Control

Your control variation should be your current, proven content. This gives you a baseline to compare against.

```
✅ Control: "Get Started Today"
✅ Variation A: "Start Your Free Trial"
✅ Variation B: "Try It Risk-Free"
```

#### 2. Test One Element at a Time

When starting out, change only one thing per variation:

**Good Single-Variable Tests**:
- Headline text only
- Button color only
- Image only
- Call-to-action wording only

**Avoid Multi-Variable Tests Initially**:
- ❌ Changing headline AND button AND image at once
- ❌ You won't know which change made the difference

#### 3. Make Meaningful Differences

Variations should be noticeably different:

**Too Similar** (hard to measure):
```
❌ "Buy Now"
❌ "Buy Today"
```

**Better** (clear differences):
```
✅ "Buy Now"
✅ "Get Yours Today - 20% Off"
```

#### 4. Name Variations Clearly

Use descriptive names that explain what's different:

**Good Names**:
- "Control - Original Headline"
- "Variation A - Benefit-Focused"
- "Variation B - Feature-Focused"
- "Variation C - Question Format"

**Poor Names**:
- "Test 1"
- "V2"
- "New"

---

## Implementing Tests on Your Site

Once you've created a campaign and variations, you need to add them to your website.

### Using Shortcodes (Primary Method)

#### Basic Shortcode

Copy the campaign ID from your campaign list and use this shortcode:

```
[ab_test campaign="123"]
```

Replace `123` with your actual campaign ID.

#### Where to Add Shortcodes

**In Posts/Pages**:
1. Edit the post or page
2. Add an HTML block or shortcode block
3. Paste your shortcode
4. Update/Publish

**In Widgets**:
1. Go to Appearance → Widgets
2. Add a Custom HTML widget
3. Paste your shortcode
4. Save

**In Theme Templates** (requires PHP knowledge):
```php
<?php echo do_shortcode('[ab_test campaign="123"]'); ?>
```

### Shortcode Parameters

Customize your shortcode with these parameters:

#### Element Type

Specify what you're testing for better tracking:

```
[ab_test campaign="123" element="headline"]
[ab_test campaign="123" element="cta-button"]
[ab_test campaign="123" element="hero-image"]
[ab_test campaign="123" element="pricing-table"]
```

#### Custom CSS Classes

Add your own CSS classes for styling:

```
[ab_test campaign="123" class="my-custom-class highlighted"]
```

#### Wrapper Element

Change the HTML wrapper (default is `<div>`):

```
[ab_test campaign="123" wrapper="section"]
[ab_test campaign="123" wrapper="span"]
[ab_test campaign="123" wrapper="none"]
```

Use `wrapper="none"` for inline content:

```
Our product is [ab_test campaign="123" wrapper="none"]amazing[/ab_test].
```

#### Debug Mode

Show debug information (useful during setup):

```
[ab_test campaign="123" debug="true"]
```

This displays:
- Campaign name and ID
- Which variation is showing
- Visitor ID
- Traffic weight percentages

#### Force Specific Variation (Testing)

Force a specific variation to preview it:

```
[ab_test campaign="123" force="2"]
[ab_test campaign="123" force="Variation A"]
```

### Real-World Examples

#### Testing a Headline

```html
<article>
  <h1>[ab_test campaign="456" element="main-headline"]</h1>
  
  <p>Rest of your content here...</p>
</article>
```

#### Testing a Call-to-Action Button

```html
<div class="cta-section">
  <h2>Ready to Get Started?</h2>
  
  [ab_test campaign="789" element="cta-button" class="text-center"]
  
  <p class="subtext">No credit card required</p>
</div>
```

#### Testing an Entire Section

```html
[ab_test campaign="101" element="hero-section" wrapper="section"]
```

#### Testing Product Features

```html
<div class="product-page">
  <div class="product-images">
    <img src="product.jpg" alt="Product">
  </div>
  
  <div class="product-info">
    <h1>Product Name</h1>
    
    [ab_test campaign="202" element="product-description"]
    
    <div class="price">$99.99</div>
    <button>Add to Cart</button>
  </div>
</div>
```

#### Testing Email Signup Forms

```html
<aside class="sidebar">
  [ab_test campaign="303" element="newsletter-signup"]
</aside>
```

### Automatic Injection

If you set a **Target Content** when creating your campaign, the test automatically appears on that page without needing a shortcode.

The test will inject at the beginning of the post content.

---

## Tracking & Analytics

### What Gets Tracked Automatically

Pronto A/B Testing automatically tracks:

#### 1. Impressions
Every time a visitor sees a variation, it's counted as an impression.

#### 2. Clicks
Clicks on:
- Links (`<a>` tags)
- Buttons
- Submit buttons
- Any clickable elements within the test

#### 3. Form Submissions
- Standard HTML forms
- Contact Form 7
- Gravity Forms
- WPForms
- Any form within the test content

#### 4. Time on Page
Engagement milestones tracked:
- 10 seconds
- 30 seconds
- 60 seconds
- 2 minutes
- 5 minutes

### Viewing Your Results

1. Go to **A/B Tests** → **All Campaigns**
2. Find your campaign
3. View the **Performance** column for quick stats

Or:

1. Click **Edit** on your campaign
2. See detailed stats in the **Campaign Statistics** box

### Understanding the Metrics

**Impressions**: Total number of times variations were shown

**Conversions**: Total number of goal completions (clicks, form submissions)

**Unique Visitors**: Number of individual people who saw the test

**Conversion Rate**: (Conversions ÷ Impressions) × 100

**Example**:
```
Variation A:
- 1,000 impressions
- 50 conversions
- Conversion rate: 5%

Variation B:
- 1,000 impressions
- 75 conversions
- Conversion rate: 7.5%

Result: Variation B performs 50% better! 🎉
```

### Setting Up Custom Goals

For advanced tracking, you can create custom goals:

1. Go to campaign settings
2. Click **Add Goal**
3. Choose goal type:
   - **Click Goal**: Track clicks on specific elements
   - **Form Goal**: Track form submissions
   - **Custom Goal**: Track using JavaScript

4. Name your goal (e.g., "Add to Cart Click")
5. Set a value (optional, for revenue tracking)
6. Save

### Manual Event Tracking

For advanced users, you can track custom events using JavaScript:

```javascript
// Track a conversion
window.abTrackConversion(campaignId, variationId, {
    type: 'newsletter_signup',
    value: 'premium_tier'
});

// Track a custom goal
window.abTrackGoal('video_watched', campaignId, variationId, 30);
```

---

## Declaring a Winner

### When to Declare a Winner

Declare a winner when:

✅ **You have enough data** - At least 100 conversions per variation
✅ **The test has run long enough** - Minimum 1-2 weeks
✅ **One variation clearly outperforms** - Significant difference in conversion rate
✅ **Results are consistent** - Winner maintains lead over time

⚠️ **Don't declare a winner too early!**

Premature conclusions can lead to wrong decisions. Let the test run until you have statistical confidence.

### How to Declare a Winner

#### Method 1: Manual Selection

1. Go to your campaign
2. Review the performance stats
3. Click the **Declare Winner** button
4. Select the winning variation
5. Choose what to do next:
   - **Apply to All Traffic**: Everyone sees the winner
   - **Archive Test**: Keep data, stop running test
   - **Create New Test**: Start a new test based on the winner

#### Method 2: Automatic Detection (Pro)

The plugin can automatically detect winners when:
- Statistical significance is reached
- Minimum sample size is met
- Minimum time period has elapsed

You'll receive an email notification when a winner is detected.

### After Declaring a Winner

Once you've declared a winner:

1. **Apply the Winner**: The winning variation shows to 100% of visitors
2. **Update Your Content**: Permanently replace the original with the winner
3. **Remove the Test**: Clean up by removing the A/B test shortcode
4. **Document Results**: Note what you learned for future tests

**Example Workflow**:

```
Before:
[ab_test campaign="123" element="headline"]

After (removing test):
<h1>Your Winning Headline Text</h1>
```

### What to Do with Losing Variations

Losing variations can still teach you:

- **Archive them** for reference
- **Analyze why they lost** - Learn from the data
- **Extract ideas** - Maybe parts of it can improve future tests
- **Don't delete immediately** - Keep data for historical records

---

## Best Practices

### 1. Start Simple

**First Test**:
```
✅ Test: Change headline text
✅ Two variations only
✅ Clear goal: increase button clicks
```

**Don't Start With**:
```
❌ Five different variations
❌ Multiple elements changing at once
❌ Unclear goals
```

### 2. Test One Thing at a Time

Change only one element per test:

**Good Single-Variable Tests**:
- Headline wording
- Button color
- Button text
- Image choice
- Call-to-action placement

**Avoid Multi-Variable (for now)**:
- Headline + button + image all at once
- You won't know what made the difference

### 3. Have a Clear Hypothesis

Before starting, write down:

**What you're testing**:
```
Testing whether a benefit-focused headline performs 
better than a feature-focused headline
```

**What you expect**:
```
Hypothesis: Benefit-focused headline will increase 
sign-up button clicks by at least 20%
```

**Why you expect it**:
```
Reason: Customer surveys indicate users care more 
about benefits than technical features
```

### 4. Give Tests Enough Time

**Minimum Test Duration**:
- ⏱️ **1 week minimum** - Account for day-of-week variations
- ⏱️ **2 weeks better** - More reliable data
- ⏱️ **Include weekends** - Different behavior patterns

**Minimum Sample Size**:
- 📊 **100 conversions per variation** minimum
- 📊 **1,000 visitors per variation** preferred

### 5. Test High-Traffic Pages First

Get results faster by testing pages with the most visitors:

**Priority Order**:
1. Homepage
2. Landing pages
3. Product/service pages
4. Pricing page
5. Blog posts (high-traffic ones)
6. About page

### 6. Document Everything

Keep notes on:
- What you tested
- Why you tested it
- Your hypothesis
- Results
- What you learned
- Next steps

**Template**:
```
Test: Homepage Headline
Date: Jan 1 - Jan 14, 2024
Variations:
  - Control: "Welcome to Our Service"
  - Test: "Transform Your Business in 30 Days"
  
Results:
  - Control: 3.2% conversion rate
  - Test: 4.8% conversion rate
  - Winner: Test variation (+50% improvement)
  
Learning: Benefit-focused messaging with 
specific timeframe resonates better
  
Next Test: Test different timeframes 
(30 days vs 60 days vs 90 days)
```

### 7. Test Regularly

Make A/B testing a habit:

- 🗓️ **Monthly**: Run at least one test per month
- 🗓️ **Quarterly**: Review all past tests, identify patterns
- 🗓️ **Annually**: Document biggest wins, share with team

### 8. Don't Test Everything

Focus on elements that will make the biggest impact:

**High-Impact Elements**:
- Main headline
- Primary CTA button
- Hero image
- Value proposition
- Pricing display

**Lower-Impact Elements** (test later):
- Footer text
- Sidebar widgets
- Social media icons
- Copyright notice

### 9. Trust the Data

Sometimes results surprise you:

- Your "ugly" design might win
- Longer copy might outperform short
- Unconventional colors might work better

**Trust the data, not your opinion.**

### 10. Consider External Factors

Be aware of:

**Seasonal Changes**:
- Holidays affect behavior
- School schedules
- Weather patterns

**Marketing Campaigns**:
- Email blasts
- Social media promotions
- Paid advertising

**Site Changes**:
- New blog posts
- Press mentions
- Platform updates

If something external happens during your test, make a note and consider extending the test duration.

---

## Troubleshooting

### Test Not Showing

**Problem**: Shortcode displays but no variations show

**Solutions**:
1. ✅ Verify campaign status is "Active"
2. ✅ Check start/end dates
3. ✅ Ensure variations are published
4. ✅ Clear your browser cache
5. ✅ Check if you're logged in as admin (might be excluded)

### Wrong Variation Showing

**Problem**: See the same variation every time

**Solutions**:
1. This is correct behavior - you're assigned to one variation consistently
2. To test other variations:
   - Use incognito/private browser window
   - Use different device
   - Use force parameter: `[ab_test campaign="123" force="2"]`
3. Clear cookies to get reassigned

### Tracking Not Working

**Problem**: No impressions or conversions recorded

**Solutions**:
1. ✅ Check browser console for JavaScript errors
2. ✅ Verify JavaScript is enabled
3. ✅ Check ad blockers aren't interfering
4. ✅ Ensure you're not testing on yourself (admins might be excluded)
5. ✅ Wait 5-10 minutes for data to appear (there's a slight delay)

### Shortcode Showing as Text

**Problem**: `[ab_test campaign="123"]` displays on page

**Solutions**:
1. ✅ Ensure plugin is activated
2. ✅ Check you're using correct shortcode syntax
3. ✅ Try using WordPress's HTML block or Shortcode block
4. ✅ Some page builders need special handling

### Performance Issues

**Problem**: Page loads slowly with A/B tests

**Solutions**:
1. ✅ Limit number of active tests per page (max 3)
2. ✅ Ensure caching is enabled
3. ✅ Check for JavaScript conflicts with other plugins
4. ✅ Optimize variation content (compress images, minimize code)

### Different Results on Mobile

**Problem**: Test shows different conversion rates on mobile vs desktop

**Solutions**:
1. This is normal - mobile users behave differently
2. Consider creating separate tests for mobile
3. Use device targeting to test mobile-specific variations

---

## FAQs

### General Questions

**Q: How long should I run a test?**

A: Minimum 1-2 weeks with at least 100 conversions per variation. The more data, the better.

**Q: Can I run multiple tests on the same page?**

A: Yes, but limit to 2-3 tests maximum per page to avoid performance issues and interaction effects.

**Q: Will A/B testing hurt my SEO?**

A: No. Search engines understand A/B testing. As long as you're not cloaking (showing different content to search engines), you're fine.

**Q: Can I test on any WordPress theme?**

A: Yes! The plugin works with any properly-coded WordPress theme.

**Q: Does this work with page builders?**

A: Yes! Works with Elementor, Divi, Beaver Builder, WPBakery, and others. Add shortcodes in HTML/shortcode widgets.

**Q: Can I pause a test?**

A: Yes, change the campaign status to "Paused". Your data is preserved and you can resume later.

### Technical Questions

**Q: Where is my data stored?**

A: All data is stored in your WordPress database on your server. Nothing is sent to external services.

**Q: Can I export my test results?**

A: Yes, use the Export button on the campaign analytics page to download CSV files.

**Q: Will this slow down my website?**

A: Minimal impact. The plugin is optimized for performance with conditional loading and caching support.

**Q: Is visitor data anonymous?**

A: Yes, visitors are tracked with anonymous IDs. No personal information is collected unless they submit a form.

**Q: Can I integrate with Google Analytics?**

A: Yes (Pro version). Integration sends A/B test data to your Google Analytics account.

### Business Questions

**Q: What's the difference between free and pro?**

A: Free version has limited campaigns and basic features. Pro includes unlimited campaigns, statistical significance calculator, advanced targeting, and priority support.

**Q: Can I upgrade from free to pro?**

A: Yes, upgrade anytime. All your campaigns and data are preserved.

**Q: Do you offer refunds?**

A: Yes, 30-day money-back guarantee if you're not satisfied.

**Q: Can I use this on client websites?**

A: Yes (check license terms). Agency/Enterprise plans include white-label options and multi-site support.

---

## Getting Help

### Support Resources

**Documentation**: [https://your-site.com/docs](https://your-site.com/docs)

**Video Tutorials**: [https://your-site.com/tutorials](https://your-site.com/tutorials)

**Community Forum**: [https://your-site.com/community](https://your-site.com/community)

**Email Support**: support@your-site.com (Pro users)

### Before Contacting Support

Please have ready:
1. Plugin version number
2. WordPress version
3. Active theme name
4. Screenshots of the issue
5. Steps to reproduce the problem
6. Browser console errors (if applicable)

### Useful Links

- 📚 [Knowledge Base](https://your-site.com/kb)
- 🎥 [Video Tutorials](https://your-site.com/videos)
- 💬 [Community Forum](https://your-site.com/forum)
- 📧 [Contact Support](https://your-site.com/support)
- 🚀 [Feature Requests](https://your-site.com/features)

---

## Conclusion

Congratulations! You now have everything you need to start running successful A/B tests on your WordPress site.

### Quick Start Checklist

- [ ] Install and activate plugin
- [ ] Create your first campaign
- [ ] Add 2 variations (control + test)
- [ ] Add shortcode to your page
- [ ] Activate campaign
- [ ] Wait 1-2 weeks for data
- [ ] Analyze results
- [ ] Declare winner
- [ ] Apply winning variation
- [ ] Start your next test!

### Remember

✅ Start simple
✅ Test one thing at a time
✅ Give tests enough time
✅ Trust the data
✅ Keep testing regularly

**Happy testing! 🚀**

---

*Version 1.0 - Last Updated: [Date]*

*For the latest version of this guide, visit: [https://your-site.com/user-guide](https://your-site.com/user-guide)*