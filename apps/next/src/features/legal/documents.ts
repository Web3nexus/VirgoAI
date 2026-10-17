// The legal pages. Text in [brackets] is a placeholder that must be filled in before launch.

export type LegalSection = { heading: string | null; paragraphs: string[] }

export type LegalDocument = { title: string; sections: LegalSection[] }

export type LegalKey = 'privacy' | 'terms' | 'acceptable-use' | 'cookies'

export const LEGAL_DOCUMENTS: Record<LegalKey, LegalDocument> = {
  "privacy": {
    "title": "Privacy policy",
    "sections": [
      {
        "heading": null,
        "paragraphs": [
          "[Company legal name] runs virgoAi, the AI content studio at [studio domain]. This policy covers what we collect when you create an account, make content, or buy credits, and what we do with it. If you are in the UK or the EU, [Company legal name] is the controller of this data. Write to [privacy email] with any question."
        ]
      },
      {
        "heading": "What we collect",
        "paragraphs": [
          "Your account. Your name, your email address, and a password. We store the password as a hash, never as plain text.",
          "What you make. Prompts, scripts, character and product descriptions, reference images, uploaded video and audio, and the outputs the studio produces from them.",
          "Payments. We never see your card number. Paddle and Flutterwave take the payment. They send us a record of it: the plan or credit pack, the amount, the date, and a reference that we match to your account.",
          "Usage. The credits each run used, the model that ran it, and the time. We need this to show your balance and to bill you correctly.",
          "Technical data. Your IP address and browser details, kept in server logs for security and debugging."
        ]
      },
      {
        "heading": "What we do with it",
        "paragraphs": [
          "We use your data to run the studio, keep your account working, bill you correctly, and stop abuse. When you start a run, we send your prompt and any media you attached to the AI model you chose, so that it can make the output. We do not sell personal data."
        ]
      },
      {
        "heading": "Who receives it",
        "paragraphs": [
          "Anthropic receives the text of your prompts and scripts, and fal.ai receives the image, video and transcription requests. Paddle and Flutterwave receive what they need to take your payment. Your outputs sit in an S3-compatible file store run by [hosting provider], on servers we operate, and account emails go through [email provider]. Each one gets only what its job needs. Each provider keeps its own terms for what it does with the data it processes, and we link to them from [link to provider terms page].",
          "YouTube links. When you paste a YouTube link, the video plays in YouTube's embedded player, so YouTube's privacy policy applies to that playback. We do not download YouTube videos."
        ]
      },
      {
        "heading": "How long we keep it",
        "paragraphs": [
          "Generated videos and images are deleted 30 days after they are made. The record stays, so your billing history is complete. A file can be deleted sooner from its production page, and the record stays either way. We keep billing and accounting records for [retention period], because tax rules require it. Server logs are kept for [log retention period]. If you delete your account, we remove your personal data within [deletion period], except what the law requires us to keep."
        ]
      },
      {
        "heading": "Cookies and browser storage",
        "paragraphs": [
          "A session token keeps you signed in. Your browser also holds a few preferences, such as which notifications you have opened. Advertising cookies are not used. The cookie policy has the details."
        ]
      },
      {
        "heading": "Your rights",
        "paragraphs": [
          "Depending on where you live, you can ask to see your data, correct it, export it, or delete it. You can also object to some uses and ask us to restrict others. Write to [privacy email]. We reply within [response period]. You may complain to your local data protection authority at any time."
        ]
      },
      {
        "heading": "Security",
        "paragraphs": [
          "We hash passwords, store payment provider keys encrypted, and send all traffic over HTTPS. No system is perfectly secure. If we learn of a breach that affects your data, we will tell you as the law requires."
        ]
      },
      {
        "heading": "International transfers",
        "paragraphs": [
          "Our AI providers and payment processors may handle your data outside your country. Where the law requires it, we rely on their standard contractual clauses or an equivalent safeguard."
        ]
      },
      {
        "heading": "Children",
        "paragraphs": [
          "virgoAi is not for anyone under [minimum age]. We do not knowingly collect data from younger people. If you think we have, write to [privacy email] and we will delete it."
        ]
      },
      {
        "heading": "Changes",
        "paragraphs": [
          "If we change this policy in a way that matters to you, we will tell you by email or in the studio before the change takes effect."
        ]
      }
    ]
  },
  "terms": {
    "title": "Terms of service",
    "sections": [
      {
        "heading": null,
        "paragraphs": [
          "These terms are a contract between you and [Company legal name]. You agree to them by creating an account or using virgoAi. You can stop using the studio at any time if you disagree with these terms."
        ]
      },
      {
        "heading": "The service",
        "paragraphs": [
          "virgoAi lets you turn an idea, a script, a video, or a YouTube link into written, image, audio and video content. Models from third-party providers make the output. The output can be wrong, can look like something that already exists, and can differ each time you run the same request. You check it before you use it."
        ]
      },
      {
        "heading": "Your account",
        "paragraphs": [
          "You must be at least [minimum age] years old, and the details you give us must be accurate. You are responsible for what happens under your account and for keeping your password private. If you suspect someone else has used it, tell us straight away."
        ]
      },
      {
        "heading": "Credits",
        "paragraphs": [
          "The studio runs on credits, shown as tokens in your balance. Each run reserves credits before it starts. When the run finishes, we charge the amount it actually used and return the rest. A run that fails returns what it reserved. Credits do not roll over unless a plan says so."
        ]
      },
      {
        "heading": "Plans and payments",
        "paragraphs": [
          "Paddle is the merchant of record for most international card payments. Flutterwave collects card and local payments in some markets, and your plan keeps renewing on the schedule you picked until you cancel it. You can cancel at any time; the cancellation takes effect at the end of the period you have paid for. Prices are shown before you pay, and taxes are added where the law requires."
        ]
      },
      {
        "heading": "Refunds",
        "paragraphs": [
          "Credits you have not used can be refunded within [refund window] of purchase. Subscription fees for a period you have started are not refunded, except where the law requires. Ask for a refund at [support email]."
        ]
      },
      {
        "heading": "What you own",
        "paragraphs": [
          "The rights you hold in what you upload stay yours. You let us store it and send it to the providers you choose, and only for that reason. As for the output, you own it to the extent the law and each provider's terms allow. Output may not be exclusive. Someone else could make something similar from a similar prompt. We do not claim ownership of your output."
        ]
      },
      {
        "heading": "What you may not do",
        "paragraphs": [
          "Do not use virgoAi to break the law, to infringe anyone's rights, or to make content that is abusive, hateful, sexual involving minors, or designed to deceive people about who they are talking to. The acceptable use policy lists the rest."
        ]
      },
      {
        "heading": "Availability",
        "paragraphs": [
          "We try to keep the service running, but it can be down for maintenance or fail because a provider fails. We may change or stop a feature with notice where we can give it."
        ]
      },
      {
        "heading": "Liability",
        "paragraphs": [
          "The service is provided as it is. To the extent the law allows, [Company legal name] is not liable for indirect loss, lost profit, or content you publish. Our total liability is limited to the fees you paid in the [liability period] before the claim. Nothing in these terms limits liability that the law does not allow us to limit."
        ]
      },
      {
        "heading": "Ending your account",
        "paragraphs": [
          "You can close your account at any time. We can suspend or close it if you break these terms, and we will say why where we can."
        ]
      },
      {
        "heading": "Governing law",
        "paragraphs": [
          "These terms are governed by the laws of [governing law]. Disputes go to the courts of [jurisdiction]."
        ]
      },
      {
        "heading": "Changes",
        "paragraphs": [
          "We may change these terms. We will tell you before a material change takes effect. If you keep using the service after that, you accept the change."
        ]
      }
    ]
  },
  "acceptable-use": {
    "title": "Acceptable use policy",
    "sections": [
      {
        "heading": null,
        "paragraphs": [
          "This policy sits under our terms of service. It lists what you must not do with virgoAi. Breaking it can lead to a warning, a removed output, or a closed account. Credits used for a run that breaks this policy are not refunded."
        ]
      },
      {
        "heading": "Do not make content that",
        "paragraphs": [
          "Sexualises minors in any way, or depicts a real person in sexual content without their consent.",
          "Threatens, harasses, or targets a private person, or encourages violence against any group.",
          "Impersonates a real person or organisation to deceive, including a fake endorsement, a fake news clip, or a fake statement from a public figure.",
          "Promotes fraud, scams, phishing, or a product you know is unsafe or illegal to sell.",
          "Gives medical, legal or financial claims as fact without a basis, or invents testimonials, reviews, ratings or results.",
          "Uses someone else's copyrighted work without the right to use it."
        ]
      },
      {
        "heading": "Do not try to",
        "paragraphs": [
          "Get around credit limits, rate limits, or payment checks, or run the studio in a way that would charge a stranger's account.",
          "Scrape the studio, or use it to build a competing service by automated bulk requests.",
          "Probe, attack, or overload our systems or the providers' systems through the studio.",
          "Share an account, or sell access to one."
        ]
      },
      {
        "heading": "Labelling",
        "paragraphs": [
          "Output made with virgoAi may need a label under the law or a platform's rules. Say so where a rule requires it. The studio labels each output with the provider and model that made it, which you should keep if a platform asks where the content came from."
        ]
      },
      {
        "heading": "Reporting",
        "paragraphs": [
          "If you see content or use that breaks this policy, write to [abuse email]. Include the link and what you saw. We review reports and reply where we can."
        ]
      }
    ]
  },
  "cookies": {
    "title": "Cookie policy",
    "sections": [
      {
        "heading": null,
        "paragraphs": [
          "virgoAi uses browser storage in a few places. This page lists what we use and why.",
          "Session token. When you sign in, the studio keeps a token in your browser so that you stay signed in. It is needed for the service to work. It ends when you sign out.",
          "Preferences. We keep a short list of production IDs you have opened, so the notifications menu knows which items are new. This stays in your browser only and never reaches our servers.",
          "Payments. When you check out, Paddle or Flutterwave may set their own cookies to process the payment. Their policies cover those cookies.",
          "We do not run advertising or cross-site tracking cookies. If we add analytics later, this page will say what it measures and offer a way to opt out where the law requires it.",
          "To clear what we store, sign out and clear the site data for the studio in your browser settings. Clearing the notification list only means items show as new again."
        ]
      }
    ]
  }
}
