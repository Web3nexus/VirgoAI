import { Link } from '@/lib/router'
import { LEGAL_DOCUMENTS, type LegalKey } from '@/features/legal/documents'

/** A legal document, in plain type. The text lives in features/legal/documents.ts. */
export function LegalPage({ doc }: { doc: LegalKey }) {
  const document = LEGAL_DOCUMENTS[doc]

  return (
    <div className="mx-auto min-h-svh w-full max-w-2xl px-4 py-12 sm:px-6">
      <Link to="/" className="text-sm text-muted-foreground hover:text-foreground">
        virgoAi
      </Link>
      <h1 className="mt-6 text-3xl font-semibold tracking-tight">{document.title}</h1>

      <div className="mt-8 flex flex-col gap-10">
        {document.sections.map((section, index) => (
          <section key={section.heading ?? index} className="flex flex-col gap-4">
            {section.heading && <h2 className="text-lg font-semibold">{section.heading}</h2>}
            {section.paragraphs.map((paragraph) => (
              <p key={paragraph.slice(0, 40)} className="text-sm leading-7 text-foreground/85">
                {paragraph}
              </p>
            ))}
          </section>
        ))}
      </div>
    </div>
  )
}
