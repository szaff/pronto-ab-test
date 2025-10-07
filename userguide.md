# Pronto A/B Testing - Complete User Guide

Welcome to Pronto A/B Testing! This guide will help you create, manage, and optimize your A/B tests to improve conversions and user engagement on your WordPress website.

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Understanding A/B Testing](#understanding-ab-testing)
3. [Creating Your First Campaign](#creating-your-first-campaign)
4. [Adding Variations](#adding-variations)
5. [Setting Up Goals](#setting-up-goals)
6. [Implementing Tests on Your Site](#implementing-tests-on-your-site)
7. [Tracking & Analytics](#tracking--analytics)
8. [Statistical Significance](#statistical-significance)
9. [Declaring a Winner](#declaring-a-winner)
10. [Best Practices](#best-practices)
11. [Troubleshooting](#troubleshooting)
12. [FAQs](#faqs)

---

## Getting Started

### What is Pronto A/B Testing?

Pronto A/B Testing is a WordPress plugin that helps you test different versions of your content to see which performs better. You can test headlines, buttons, images, entire page sections, and more - all without coding.

### Key Features

- ✅ **Easy Shortcode System** - Add tests anywhere with simple shortcodes
- ✅ **Visual Editor Support** - Create variations using WordPress's familiar editor
- ✅ **Automatic Tracking** - Clicks, forms, and conversions tracked automatically
- ✅ **Custom Goals System** - Track specific actions like clicks, forms, page views, and revenue
- ✅ **Statistical Significance** - Built-in calculator with confidence levels
- ✅ **Winner Declaration** - Manual or automatic winner detection
- ✅ **Analytics Dashboard** - Visual charts and conversion rate tracking
- ✅ **Privacy-First** - All data stays on your server
- ✅ **No External Services** - Works entirely within WordPress

### System Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher
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

**Goal**: The specific action you want visitors to complete (click, form submit, purchase, etc.)

**Impression**: When a visitor sees one of your test variations

**Conversion**: When a visitor completes your desired goal

**Traffic Split**: How you divide visitors between variations (e.g., 50/50)

**Statistical Significance**: Confidence that your results aren't due to chance (measured at 90%, 95%, or 99%)

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
Goal: Increase newsletter signup conversions
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

Click **Save Draft** to save your campaign. You can activate it later after adding variations and goals.

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

## Setting Up Goals

Goals are specific actions you want visitors to complete during your A/B test. The new custom goals system allows you to track multiple types of conversions beyond basic clicks.

### Accessing Goals

1. Navigate to **A/B Tests** → **Goals** in the WordPress admin
2. Click **Add New Goal** to create your first goal

### Creating a Goal

#### Step 1: Basic Information

**Goal Name** (required): Descriptive name for the goal
- Examples: "Newsletter Signup", "Add to Cart", "Download Whitepaper", "Video Watched"

**Description** (optional): Notes about what this goal tracks
- Example: "Tracks when users click the main newsletter signup button in the hero section"

#### Step 2: Choose Goal Type

Select the type of action you want to track:

**Conversion** - General conversion tracking (default)

**Click** - Track clicks on specific buttons or links
- Use for: CTA buttons, download links, product links

**Form** - Track form submissions
- Use for: Contact forms, signup forms, survey submissions

**Page View** - Track when visitors reach specific pages
- Use for: Thank you pages, confirmation pages, product pages

**Custom Event** - Track custom JavaScript events
- Use for: Video plays, scroll depth, time on page

**Revenue** - Track monetary goals with values
- Use for: Purchases, donations, upgrades

#### Step 3: Configure Tracking Method

Choose how the goal should be tracked:

**Manual (API)** - Track using JavaScript code
```javascript
// You'll call this in your theme/plugin
abTrackGoal('Newsletter Signup', campaignId, variationId);
```
- Best for: Custom integrations, complex logic, third-party tools

**CSS Selector** - Automatically track clicks on specific elements
```
Tracking Value: .newsletter-button
Tracking Value: #signup-form
Tracking Value: button[type="submit"]
```
- Best for: Buttons, links, form submits within A/B test content

**URL Pattern** - Automatically track when visitors reach a URL
```
Tracking Value: /thank-you
Tracking Value: /confirmation
Tracking Value: /checkout/success
```
- Best for: Confirmation pages, thank you pages, specific destinations

**Automatic** - Let the system detect conversions automatically
- Best for: Standard conversion tracking

#### Step 4: Set Default Value (Optional)

For revenue goals, set a default monetary value:
```
Default Value: 49.99
```

This value is used unless you pass a custom value when tracking.

#### Step 5: Save

Click **Create Goal** to save. The goal is now available to assign to campaigns.

### Assigning Goals to Campaigns

Once you've created goals, assign them to your campaigns:

1. **Edit your campaign**
2. Find the **Goals** metabox in the right sidebar
3. **Select a goal** from the dropdown
4. Click **Add Goal**

**Primary Goals**:
- The first goal you assign is automatically marked as "Primary"
- Primary goal is the main success metric for winner detection
- To change primary goal, click **Make Primary** on a different goal

**Managing Goals**:
- **Remove** - Remove a goal from the campaign (data is preserved)
- **Make Primary** - Set as the primary success metric

### Goal Examples

#### Example 1: Newsletter Signup (Automatic Selector)

```
Name: Newsletter Signup
Type: Form
Method: CSS Selector
Value: #newsletter-form
Description: Main newsletter signup form in hero section
```

The plugin automatically tracks when someone submits this form inside A/B test content.

#### Example 2: Product Purchase (URL)

```
Name: Product Purchase
Type: Revenue
Method: URL Pattern
Value: /order-confirmation
Default Value: 99.00
Description: Tracks completed purchases
```

Automatically tracks when visitors reach the confirmation page.

#### Example 3: Video Engagement (Manual API)

```
Name: Video Watched 30s
Type: Custom Event
Method: Manual
Description: Tracks when visitor watches 30+ seconds of video
```

Then in your theme/plugin:
```javascript
// When video hits 30 seconds
videoElement.addEventListener('timeupdate', function() {
    if (videoElement.currentTime >= 30 && !tracked) {
        abTrackGoal('Video Watched 30s', campaignId, variationId);
        tracked = true;
    }
});
```

#### Example 4: Add to Cart (Selector + Value)

```
Name: Add to Cart
Type: Conversion
Method: CSS Selector
Value: .add-to-cart-button
Description: Tracks add to cart clicks
```

### Multiple Goals Per Campaign

You can assign multiple goals to track different success metrics:

**Example Campaign Goals**:
1. **Newsletter Signup** (Primary) - Main conversion goal
2. **Social Share** (Secondary) - Engagement metric
3. **Video Play** (Secondary) - Content interaction
4. **Download PDF** (Secondary) - Lead magnet

The primary goal is used for statistical significance and winner detection, while secondary goals provide additional insights.

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
- Active goals

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

#### 4. Custom Goals
- CSS selector-based goals (automatic)
- URL-based goals (automatic)
- Manual API calls (via JavaScript)
- Revenue tracking with values

#### 5. Time on Page
Engagement milestones tracked:
- 10 seconds
- 30 seconds
- 60 seconds
- 2 minutes
- 5 minutes

### Viewing Your Results

#### Campaign List View

1. Go to **A/B Tests** → **All Campaigns**
2. Find your campaign
3. View the **Performance** column for quick stats

#### Detailed Campaign View

1. Click **Edit** on your campaign
2. See detailed stats in the **Campaign Statistics** box

#### Analytics Dashboard

1. Go to **A/B Tests** → **Analytics**
2. Select your campaign
3. Choose date range
4. View:
   - Conversion rate charts
   - Statistical significance
   - Goal performance
   - Time series data
   - CSV export option

### Understanding the Metrics

**Impressions**: Total number of times variations were shown

**Conversions**: Total number of goal completions

**Unique Visitors**: Number of individual people who saw the test

**Conversion Rate**: (Conversions ÷ Impressions) × 100

**Lift**: Percentage improvement over control
- Example: +25% lift means 25% better performance

**Statistical Significance**: Confidence level that results are real
- 90% confidence = 90% sure results aren't due to chance
- 95% confidence = industry standard (recommended)
- 99% confidence = very high confidence

**Example**:
```
Variation A (Control):
- 1,000 impressions
- 50 conversions
- Conversion rate: 5.0%

Variation B:
- 1,000 impressions
- 75 conversions
- Conversion rate: 7.5%
- Lift: +50%
- Significance: 95% confidence

Result: Variation B performs 50% better with 95% confidence! 🎉
```

### Viewing Goal Performance

In the campaign analytics:

1. **Goals Section** shows performance per goal:
   - Goal name and type
   - Conversions per variation
   - Conversion rate per variation
   - Statistical significance per goal
   - Revenue totals (for revenue goals)

2. **Primary Goal** is highlighted and used for winner detection

3. **Secondary Goals** provide additional insights

### Manual Event Tracking

For advanced users, you can track custom events using JavaScript:

#### Track a Custom Goal

```javascript
// Basic goal tracking
abTrackGoal('Newsletter Signup', campaignId, variationId);

// Goal tracking with value (for revenue)
abTrackGoal('Product Purchase', campaignId, variationId, 99.99);
```

#### Track Custom Conversion

```javascript
// Track a conversion
window.abTrackConversion(campaignId, variationId, {
    type: 'newsletter_signup',
    value: 'premium_tier'
});
```

#### Track Engagement

```javascript
// Track engagement event
window.abTrackEngagement(campaignId, variationId, {
    action: 'video_watched',
    duration: 45
});
```

### Automatic Goal Tracking

Goals with **CSS Selector** or **URL Pattern** tracking methods work automatically:

**Selector Example**:
```
Goal: Newsletter Signup
Method: CSS Selector
Value: .newsletter-button

// Automatically tracks when visitor clicks .newsletter-button inside A/B test content
// No code needed!
```

**URL Example**:
```
Goal: Thank You Page
Method: URL Pattern
Value: /thank-you

// Automatically tracks when visitor reaches /thank-you page
// Works for all active campaigns on that visitor's journey
```

---

## Statistical Significance

### What is Statistical Significance?

Statistical significance tells you how confident you can be that your test results are real and not due to random chance.

### Confidence Levels

**90% Confidence** - Good enough for low-risk decisions
- 10% chance results are due to chance
- Suitable for: Minor design tweaks, low-impact changes

**95% Confidence** ⭐ - Industry standard (recommended)
- 5% chance results are due to chance
- Suitable for: Most A/B tests, moderate-impact changes

**99% Confidence** - Very high confidence for critical decisions
- 1% chance results are due to chance
- Suitable for: Major redesigns, high-impact changes, large investments

### Reading Statistical Results

The plugin shows you:

#### Result Interpretation

```
Variation B is performing better with 95% confidence.
It shows a 42.3% increase in conversion rate.
```

This means:
- Variation B is winning
- You can be 95% confident this is real (not random)
- Variation B converts 42.3% better than control

#### Data Sufficiency Check

```
✅ Sufficient data collected
Recommended: 385 conversions per variation
Current: 450 conversions per variation
```

or

```
⚠️ More data needed
Recommended: 385 conversions per variation
Current: 127 conversions per variation
Continue test to gather more data
```

### When Results Are NOT Significant

```
The difference between variations is not yet statistically significant.
Continue testing to gather more data.
```

This means:
- One variation may be ahead
- But you can't be confident it's real yet
- Need more visitors/conversions
- **Don't declare a winner yet!**

### How Much Data Do You Need?

Minimum recommendations:
- **100-150 conversions per variation** - Bare minimum
- **250-500 conversions per variation** - Better
- **1,000+ conversions per variation** - Ideal

### P-Value

The p-value indicates probability of results being due to chance:

- **p < 0.10** = 90% confidence (marginally significant)
- **p < 0.05** = 95% confidence (significant) ⭐
- **p < 0.01** = 99% confidence (highly significant)

Lower p-values are better!

---

## Declaring a Winner

### When to Declare a Winner

Declare a winner when **ALL** of these conditions are met:

✅ **Statistical Significance Reached** - 95%+ confidence recommended
✅ **Sufficient Data Collected** - Meet minimum sample size recommendations
✅ **Test Run Long Enough** - Minimum 1-2 weeks
✅ **Clear Winner Emerges** - Consistent performance advantage
✅ **Results Are Stable** - Winner maintains lead over time

⚠️ **Don't declare a winner too early!**

Premature conclusions can lead to wrong decisions. Let the test run until you have statistical confidence.

### Automatic Winner Detection

The plugin can automatically suggest a winner when:

1. **Statistical significance is reached** (95% confidence)
2. **Minimum sample size is met** (based on baseline conversion rate)
3. **Minimum time period has elapsed** (configurable)
4. **Results are consistent** (winner maintained for specified period)

You'll see a notification in the campaign editor:

```
🏆 Winner Detected!
Variation B is performing significantly better
95% confidence with 42.3% lift
Recommend declaring Variation B as winner
```

### How to Declare a Winner

#### Method 1: Manual Declaration

1. Go to your campaign edit page
2. Review the **Statistical Significance** box
3. Click **Declare Winner** button
4. Select the winning variation
5. Choose what to do next:
   - **Apply to 100% Traffic** - Show winner to all visitors
   - **Archive Campaign** - Stop test, preserve data
   - **Continue Testing** - Keep gathering data

6. Add notes (optional) about why you chose this winner
7. Confirm

#### Method 2: Accept Automatic Suggestion

When the plugin detects a winner:

1. Review the recommendation
2. Verify the data looks correct
3. Click **Accept Recommendation**
4. Choose what to do with the winner

### After Declaring a Winner

Once you've declared a winner:

#### Option 1: Apply Winner (Recommended)

1. **Apply to 100% Traffic** - The winning variation shows to all visitors
2. **Monitor Performance** - Watch for 1-2 weeks to confirm sustained performance
3. **Permanently Update Content** - Replace original content with winner
4. **Remove A/B Test** - Clean up by removing the shortcode
5. **Archive Campaign** - Keep data for historical records

**Example Workflow**:

```
Before:
[ab_test campaign="123" element="headline"]

After applying winner:
<h1>Your Winning Headline Text Here</h1>
```

#### Option 2: Start New Test

Use the winner as your new control:

1. Create a new campaign
2. Use the winner as the control variation
3. Create new test variations
4. Test even better improvements

This creates a **continuous optimization cycle**.

### Winner Dashboard

After declaring winners, view them in:

**A/B Tests** → **Campaigns** → Filter by **Status: Completed**

See:
- Which variation won
- Performance lift
- Confidence level
- Test duration
- Total conversions
- When winner was declared

### What to Do with Losing Variations

Losing variations can still teach you:

- **Archive them** for reference
- **Analyze why they lost** - Learn from the data
  - Was the message unclear?
  - Did the design not resonate?
  - Was the offer not compelling?
- **Extract ideas** - Maybe parts can improve future tests
- **Don't delete** - Keep data for historical analysis

---

## Best Practices

### 1. Start Simple

**First Test**:
```
✅ Test: Change headline text
✅ Two variations only
✅ Clear goal: increase newsletter signups
✅ Single page/element
```

**Don't Start With**:
```
❌ Five different variations
❌ Multiple elements changing at once
❌ Testing on multiple pages simultaneously
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
- Form length

**Avoid Multi-Variable (for now)**:
- Headline + button + image all at once
- You won't know what made the difference

Once you're experienced, you can test multiple elements, but start simple.

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
newsletter signups by at least 20%
```

**Why you expect it**:
```
Reason: Customer surveys indicate users care more
about benefits than technical features
```

### 4. Set Up Goals Before Launching

Don't launch a test without clear goals:

1. **Identify your primary goal** - The main success metric
2. **Create the goal in the system** - Use the Goals page
3. **Assign to campaign** - Before activating
4. **Add secondary goals** (optional) - For additional insights
5. **Verify tracking** - Test that goals fire correctly

### 5. Give Tests Enough Time

**Minimum Test Duration**:
- ⏱️ **1 week minimum** - Account for day-of-week variations
- ⏱️ **2 weeks better** - More reliable data
- ⏱️ **Include weekends** - Different behavior patterns
- ⏱️ **Consider seasonality** - Holidays, events, etc.

**Minimum Sample Size**:
- 📊 **100 conversions per variation** minimum
- 📊 **250+ conversions per variation** better
- 📊 **1,000+ visitors per variation** preferred

**Wait for Statistical Significance**:
- Don't call a winner early
- Wait for 95% confidence
- Verify results are stable

### 6. Test High-Traffic Pages First

Get results faster by testing pages with the most visitors:

**Priority Order**:
1. Homepage - Highest traffic
2. Landing pages - High-intent visitors
3. Product/service pages - Purchase consideration
4. Pricing page - Conversion decision point
5. Blog posts (high-traffic ones) - Top of funnel
6. About page - Trust-building

### 7. Test High-Impact Elements First

Focus on elements that will make the biggest difference:

**High-Impact Elements** (test first):
- Main headline
- Primary CTA button
- Hero image
- Value proposition
- Pricing display
- Product images
- Key benefits section

**Lower-Impact Elements** (test later):
- Footer text
- Sidebar widgets
- Social media icons
- Copyright notice
- Secondary navigation

### 8. Use Multiple Goals Wisely

**Primary Goal** - Your main success metric
```
Example: Newsletter Signup
Why: This is the main conversion we're optimizing for
```

**Secondary Goals** - Additional insights
```
Examples:
- Social Share (engagement)
- Video Play (content interaction)
- Time on Page (engagement depth)
- Link Clicks (interest signals)
```

Don't optimize for secondary goals over primary - they're for learning, not decision-making.

### 9. Document Everything

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

Hypothesis:
Benefit-focused messaging will increase signups by 20%
because customer feedback shows benefits > features

Variations:
  - Control: "Welcome to Our Service"
  - Test: "Transform Your Business in 30 Days"

Goals:
  - Primary: Newsletter Signup
  - Secondary: Video Play, Social Share

Results:
  - Control: 3.2% conversion rate (160/5000)
  - Test: 4.8% conversion rate (240/5000)
  - Winner: Test variation (+50% improvement)
  - Confidence: 99%
  - Duration: 14 days

Learning:
Benefit-focused messaging with specific timeframe
resonates much better. Quantifiable results (30 days)
add credibility and urgency.

Next Test:
Test different timeframes
(30 days vs 60 days vs 90 days)
to find optimal urgency level
```

### 10. Test Regularly

Make A/B testing a habit:

- 🗓️ **Monthly**: Run at least one test per month
- 🗓️ **Quarterly**: Review all past tests, identify patterns
- 🗓️ **Annually**: Document biggest wins, share with team

Build a culture of testing and optimization.

### 11. Trust the Data

Sometimes results surprise you:

- Your "ugly" design might win
- Longer copy might outperform short
- Unconventional colors might work better
- Higher prices might convert better
- Weird CTAs might perform best

**Trust the data, not your opinion.**

The data is showing you what your actual visitors prefer, not what you think they should prefer.

### 12. Consider External Factors

Be aware of:

**Seasonal Changes**:
- Holidays affect behavior
- School schedules
- Weather patterns
- Industry cycles

**Marketing Campaigns**:
- Email blasts
- Social media promotions
- Paid advertising campaigns
- PR/press mentions

**Site Changes**:
- New blog posts going viral
- Platform updates
- Technical issues
- Design changes elsewhere

If something external happens during your test, make a note and consider extending the test duration or marking the data as potentially affected.

### 13. Don't Test Too Many Things at Once

**Maximum per page**:
- 1-2 active tests per page (ideal)
- 3 tests maximum (if necessary)

Too many tests can:
- Slow down your site
- Confuse visitors
- Create interaction effects (tests affecting each other)
- Dilute your traffic (slower results)

### 14. Mobile vs Desktop

Consider device differences:

- Mobile users behave differently
- Conversion rates often differ by device
- Test mobile experiences separately if traffic allows
- Consider creating device-specific variations

**Tip**: View your analytics by device type to understand if you need separate mobile tests.

---

## Troubleshooting

### Test Not Showing

**Problem**: Shortcode displays but no variations show

**Solutions**:
1. ✅ Verify campaign status is "Active"
2. ✅ Check start/end dates
3. ✅ Ensure variations are published (not draft)
4. ✅ Verify you have at least 2 variations
5. ✅ Clear your browser cache
6. ✅ Clear WordPress cache (if using caching plugin)
7. ✅ Check if you're logged in as admin (might be excluded in settings)

### Wrong Variation Showing

**Problem**: See the same variation every time

**Solutions**:
1. This is correct behavior - you're assigned to one variation consistently
2. To test other variations:
   - Use incognito/private browser window
   - Use different device
   - Use force parameter: `[ab_test campaign="123" force="2"]`
3. Clear cookies to get reassigned randomly

### Goals Not Tracking

**Problem**: Goals aren't recording conversions

**Solutions**:
1. ✅ Verify goal is assigned to the campaign (check Goals metabox)
2. ✅ Check goal status is "Active"
3. ✅ For selector-based goals:
   - Verify the CSS selector exists on the page
   - Ensure element is inside A/B test content (`.pronto-ab-content`)
   - Check browser console for errors
4. ✅ For URL-based goals:
   - Verify URL pattern matches the page you're on
   - Check exact URL in browser address bar
5. ✅ For manual goals:
   - Verify JavaScript syntax is correct
   - Check browser console for errors
   - Ensure `abTrackGoal()` is being called
6. ✅ Wait 5-10 minutes for data to appear (slight delay)

### Statistical Significance Not Showing

**Problem**: "Not yet significant" message persists

**Solutions**:
1. ✅ This is normal - you need more data
2. ✅ Check sample size recommendations
3. ✅ Wait for more conversions (typically 100-250+ per variation)
4. ✅ If difference is very small, may need 1000+ conversions
5. ✅ Consider if your variations are different enough
6. ✅ Verify goals are tracking correctly

### Tracking Not Working

**Problem**: No impressions or conversions recorded

**Solutions**:
1. ✅ Check browser console for JavaScript errors
2. ✅ Verify JavaScript is enabled
3. ✅ Check ad blockers aren't interfering
4. ✅ Ensure you're not testing on yourself (check visitor exclusions)
5. ✅ Verify AJAX requests are working (check Network tab in DevTools)
6. ✅ Check server error logs for PHP errors
7. ✅ Test with `debug="true"` parameter
8. ✅ Wait 5-10 minutes for data to appear

### Shortcode Showing as Text

**Problem**: `[ab_test campaign="123"]` displays on page

**Solutions**:
1. ✅ Ensure plugin is activated
2. ✅ Check you're using correct shortcode syntax
3. ✅ Try using WordPress's HTML block or Shortcode block
4. ✅ Some page builders need special handling:
   - Elementor: Use Shortcode widget
   - Divi: Use Code module
   - Beaver Builder: Use HTML module
5. ✅ Verify no extra spaces in shortcode

### Performance Issues

**Problem**: Page loads slowly with A/B tests

**Solutions**:
1. ✅ Limit number of active tests per page (max 3)
2. ✅ Ensure caching is enabled (WordPress caching plugin)
3. ✅ Check for JavaScript conflicts with other plugins
4. ✅ Optimize variation content:
   - Compress images
   - Minimize code
   - Remove unnecessary plugins
5. ✅ Check database performance (optimize tables)
6. ✅ Consider upgrading hosting if on shared hosting

### Different Results on Mobile

**Problem**: Test shows different conversion rates on mobile vs desktop

**Solutions**:
1. This is normal - mobile users behave differently
2. Mobile typically has lower conversion rates
3. Consider creating separate mobile-optimized tests
4. Use device targeting to test mobile-specific variations
5. Review mobile analytics separately
6. Optimize mobile experience specifically

### Winner Auto-Detection Not Working

**Problem**: System doesn't suggest a winner even with significant results

**Solutions**:
1. ✅ Verify auto-detection is enabled in campaign settings
2. ✅ Check if minimum requirements are met:
   - 95% statistical significance reached
   - Minimum sample size achieved
   - Minimum test duration passed
3. ✅ Ensure results have been consistent for the required period
4. ✅ Check if there's a clear winner (results must be significant)
5. ✅ Manually review data - you can always declare winner manually

---

## FAQs

### General Questions

**Q: How long should I run a test?**

A: Minimum 1-2 weeks with at least 100-250 conversions per variation. Wait for 95% statistical significance. The more data, the better.

**Q: Can I run multiple tests on the same page?**

A: Yes, but limit to 1-2 tests per page (maximum 3). Too many tests slow results and can create interaction effects.

**Q: Will A/B testing hurt my SEO?**

A: No. Search engines understand A/B testing. As long as you're not cloaking (showing different content to search engines than to users), you're fine. We serve consistent content to crawlers.

**Q: Can I test on any WordPress theme?**

A: Yes! The plugin works with any properly-coded WordPress theme.

**Q: Does this work with page builders?**

A: Yes! Works with:
- Elementor (use Shortcode widget)
- Divi (use Code module)
- Beaver Builder (use HTML module)
- WPBakery (use Raw HTML)
- Gutenberg (use Shortcode block)
- Classic Editor (paste directly)

**Q: Can I pause a test?**

A: Yes, change the campaign status to "Paused". Your data is preserved and you can resume later. Useful if you need to make site changes or handle unexpected events.

**Q: Can I edit variations after launching?**

A: Yes, but be careful:
- Edit variations before they have significant data
- Major changes should warrant starting a new test
- Minor fixes (typos, broken links) are fine
- Track what you changed in campaign notes

**Q: How many goals can I assign to a campaign?**

A: Unlimited! Assign as many goals as you want to track. Mark one as primary for winner detection, use others for insights.

### Technical Questions

**Q: Where is my data stored?**

A: All data is stored in your WordPress database on your server. Nothing is sent to external services. You maintain complete control and privacy.

**Q: Can I export my test results?**

A: Yes, use the Export button on the Analytics dashboard to download CSV files with your test data.

**Q: Will this slow down my website?**

A: Minimal impact. The plugin is optimized for performance with:
- Conditional loading (only loads on pages with active tests)
- Caching support
- Efficient database queries
- Lightweight JavaScript

**Q: Is visitor data anonymous?**

A: Yes, visitors are tracked with anonymous IDs (not IP addresses). No personal information is collected unless they submit a form with PII.

**Q: Can I integrate with Google Analytics?**

A: Yes! (Coming in future update) Integration will send A/B test data to your Google Analytics account as custom dimensions.

**Q: Does it work with caching plugins?**

A: Yes, compatible with:
- WP Super Cache
- W3 Total Cache
- WP Rocket
- LiteSpeed Cache
- Others

Tests still work because visitor assignment happens via JavaScript, not server-side.

**Q: Can I use custom tracking code?**

A: Yes! Use the JavaScript API:
```javascript
abTrackGoal('Goal Name', campaignId, variationId, value);
abTrackConversion(campaignId, variationId, data);
abTrackEngagement(campaignId, variationId, data);
```

### Goals & Tracking Questions

**Q: What's the difference between a conversion and a goal?**

A:
- **Conversion**: Generic success event (clicks, form submits)
- **Goal**: Specific, named action you're tracking (e.g., "Newsletter Signup", "Product Purchase")

Goals are more specific and let you track multiple success metrics per test.

**Q: Can I track the same goal across multiple campaigns?**

A: Yes! Create a goal once, assign it to multiple campaigns. Perfect for standard conversions like newsletter signups that you test in different contexts.

**Q: Can I track revenue/monetary values?**

A: Yes! Use Revenue-type goals and pass values:
```javascript
abTrackGoal('Product Purchase', campaignId, variationId, 99.99);
```

Analytics will show total revenue and average order value per variation.

**Q: Do selector-based goals work outside the A/B test content?**

A: No, selector goals only track clicks within the A/B test content area (`.pronto-ab-content`). This ensures goals are attributed to the correct variation.

For tracking outside the test area, use manual API tracking or URL-based goals.

**Q: Can I track form submissions?**

A: Yes, multiple ways:
1. **Automatic**: Forms within test content are tracked automatically
2. **Selector goal**: Target form submit button with CSS selector
3. **URL goal**: Track thank-you page after submission
4. **Manual API**: Call `abTrackGoal()` on form success

**Q: How do I track WooCommerce purchases?**

A: Create a URL-based goal:
```
Goal: Product Purchase
Type: Revenue
Method: URL Pattern
Value: /checkout/order-received
```

For advanced tracking with order values, use the manual API with WooCommerce hooks.

### Statistical Questions

**Q: What does "95% confidence" mean?**

A: You can be 95% confident the winning variation is actually better, not just lucky. Only a 5% chance the result is due to random chance.

**Q: Why is my test not reaching significance?**

A: Common reasons:
- Not enough data yet (need more visitors/conversions)
- Variations are too similar (not enough difference to detect)
- Conversion rates are already optimized (hard to improve further)
- External factors causing noise (seasonality, campaigns)

**Q: Can a losing variation become the winner?**

A: Yes! Early in a test, the leader can change. This is why we wait for statistical significance. Once you reach 95%+ confidence with sufficient data, the winner is very unlikely to change.

**Q: Should I trust a 90% confidence result?**

A: 90% is marginal. We recommend:
- 95% for most tests (standard)
- 99% for critical, high-impact decisions

90% means 10% chance of being wrong - too risky for most business decisions.

**Q: What sample size do I need?**

A: It depends on:
- Your baseline conversion rate
- The difference between variations
- Your desired confidence level

The plugin calculates this for you and shows recommendations in the Statistical Significance box.

Generally: 100-250+ conversions per variation for most tests.

### Business Questions

**Q: Do I need the Pro version?**

A: Free version includes:
- 1 active campaign
- 2 variations per campaign
- Basic analytics
- All goal tracking features
- Statistical significance calculator

Pro adds:
- Unlimited campaigns
- Unlimited variations
- Advanced analytics dashboard
- Automatic winner detection
- Team collaboration
- Priority support

**Q: Can I upgrade from free to pro?**

A: Yes, upgrade anytime. All your campaigns, variations, goals, and data are preserved.

**Q: Do you offer refunds?**

A: Yes, 30-day money-back guarantee if you're not satisfied.

**Q: Can I use this on client websites?**

A: Yes (check license terms). Agency/Enterprise plans include:
- White-label options
- Multi-site support
- Client reporting
- Team management

**Q: Is there a trial period?**

A: Free version is full-featured with limitations. Try it risk-free before upgrading to Pro.

---

## Getting Help

### Support Resources

**Documentation**: [Full documentation and guides]

**Video Tutorials**: [Step-by-step video walkthroughs]

**Community Forum**: [Connect with other users]

**Email Support**: support@pronto-ab.com (Pro users)

### Before Contacting Support

Please have ready:
1. Plugin version number (A/B Tests → About)
2. WordPress version (Dashboard → Updates)
3. Active theme name (Appearance → Themes)
4. PHP version (Tools → Site Health)
5. Screenshots of the issue
6. Steps to reproduce the problem
7. Browser console errors (if applicable - F12 → Console tab)
8. Campaign ID (if issue is with specific campaign)

### Useful Links

- 📚 [Knowledge Base](#)
- 🎥 [Video Tutorials](#)
- 💬 [Community Forum](#)
- 📧 [Contact Support](#)
- 🚀 [Feature Requests](#)
- 🐛 [Report Bug](#)

---

## Conclusion

Congratulations! You now have everything you need to start running successful A/B tests on your WordPress site with custom goal tracking, statistical significance analysis, and winner detection.

### Quick Start Checklist

- [ ] Install and activate plugin
- [ ] Create your first goal (e.g., "Newsletter Signup")
- [ ] Create your first campaign
- [ ] Add 2 variations (control + test)
- [ ] Assign goal to campaign (mark as primary)
- [ ] Add shortcode to your page
- [ ] Activate campaign
- [ ] Verify goal tracking works (check in real-time)
- [ ] Wait 1-2 weeks for statistical significance
- [ ] Analyze results (check stats dashboard)
- [ ] Declare winner
- [ ] Apply winning variation
- [ ] Start your next test!

### Key Takeaways

✅ **Start simple** - Single element, 2 variations, clear goal
✅ **Use goals** - Track specific actions, not just generic conversions
✅ **Test one thing at a time** - Isolate variables to learn what works
✅ **Wait for significance** - Don't call winners early (95% confidence)
✅ **Give tests enough time** - Minimum 1-2 weeks, 100-250+ conversions
✅ **Trust the data** - Not your opinions
✅ **Keep testing regularly** - Continuous optimization is key
✅ **Document everything** - Learn from every test

### What Makes Pronto A/B Different

🎯 **Custom Goals System** - Track multiple specific actions per test
📊 **Statistical Rigor** - Built-in significance calculator with proper math
🏆 **Smart Winner Detection** - Automatic suggestions when confidence is reached
🔒 **Privacy-First** - All data on your server, nothing sent externally
⚡ **Performance-Optimized** - Fast, efficient, caching-compatible
🎨 **Beautiful Analytics** - Visual charts, time series, goal breakdown

**Happy testing! 🚀**

---

## Changelog

### Version 1.2.0 - Goals & Statistical Significance
**New Features**:
- ✨ Custom Goals System with 6 goal types
- ✨ Statistical Significance Calculator (90%, 95%, 99% confidence)
- ✨ Winner Declaration System (manual + automatic)
- ✨ Analytics Dashboard with charts
- ✨ Goal tracking (selector, URL, manual API)
- ✨ Revenue tracking with values
- ✨ Multiple goals per campaign
- ✨ Primary goal designation
- ✨ CSV export

### Version 1.1.0 - Initial Release
- ✅ Campaign management
- ✅ Variation system
- ✅ Shortcode implementation
- ✅ Basic tracking (impressions, clicks, forms)
- ✅ Traffic splitting
- ✅ Visitor assignment

---

*Last Updated: 2025*

*For the latest version of this guide and updates, visit your WordPress admin → A/B Tests → Help*
