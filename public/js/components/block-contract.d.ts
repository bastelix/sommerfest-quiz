export type BlockType =
  | 'hero'
  | 'feature_list'
  | 'process_steps'
  | 'testimonial'
  | 'rich_text'
  | 'info_media'
  | 'cta'
  | 'stat_strip'
  | 'audience_spotlight'
  | 'package_summary'
  | 'faq'
  | 'contact_form'
  | 'system_module'
  | 'case_showcase';

export type HeroVariant = 'centered-cta' | 'media-right' | 'media-left' | 'minimal';
export type FeatureListVariant = 'text-columns' | 'card-stack' | 'grid-bullets' | 'detailed-cards';
export type ProcessStepsVariant = 'numbered-vertical' | 'numbered-horizontal';
export type TestimonialVariant = 'single-quote' | 'quote-wall';
export type RichTextVariant = 'prose';
export type InfoMediaVariant = 'stacked' | 'image-left' | 'image-right' | 'switcher';
export type CtaVariant = 'split' | 'full-width';
export type StatStripVariant = 'inline' | 'cards' | 'centered' | 'three-up';
export type AudienceSpotlightVariant = 'tabs' | 'tiles' | 'single-focus';
export type PackageSummaryVariant = 'toggle' | 'comparison-cards';
export type FaqVariant = 'accordion';
export type ContactFormVariant = 'default' | 'compact';
export type SystemModuleVariant = 'switcher';
export type CaseShowcaseVariant = 'tabs';

export type BlockVariant =
  | HeroVariant
  | FeatureListVariant
  | ProcessStepsVariant
  | TestimonialVariant
  | RichTextVariant
  | InfoMediaVariant
  | CtaVariant
  | StatStripVariant
  | AudienceSpotlightVariant
  | PackageSummaryVariant
  | FaqVariant
  | ContactFormVariant
  | SystemModuleVariant
  | CaseShowcaseVariant;

export interface Tokens {
  background?: 'primary' | 'secondary' | 'muted' | 'accent' | 'surface';
  spacing?: 'small' | 'normal' | 'large';
  width?: 'narrow' | 'normal' | 'wide';
  columns?: 'single' | 'two' | 'three' | 'four';
  accent?: 'brandA' | 'brandB' | 'brandC';
}

export interface Media {
  imageId?: string;
  image?: string;
  alt?: string;
  focalPoint?: { x: number; y: number };
}

export interface CallToAction {
  label: string;
  href: string;
  ariaLabel?: string;
}

export type CallToActionGroup =
  | CallToAction
  | {
      primary: CallToAction;
      secondary?: CallToAction;
    };

export interface CtaBlockData {
  title?: string;
  body?: string;
  primary: CallToAction;
  secondary?: CallToAction;
}

export interface HeroBlockData {
  eyebrow?: string;
  headline: string;
  subheadline?: string;
  media?: Media;
  cta: CallToActionGroup;
}

export interface FeatureItem {
  id: string;
  icon?: string;
  title: string;
  description: string;
  label?: string;
  bullets?: string[];
  media?: Media;
}

export interface FeatureListBlockData {
  eyebrow?: string;
  title: string;
  subtitle?: string;
  lead?: string;
  intro?: string;
  items: FeatureItem[];
  cta?: CallToAction;
}

export interface ProcessStep {
  id: string;
  title: string;
  description: string;
  duration?: string;
  media?: Media;
}

export interface ClosingCopy {
  title?: string;
  body?: string;
}

export interface ProcessStepsBlockData {
  title: string;
  summary?: string;
  intro?: string;
  steps: ProcessStep[];
  closing?: ClosingCopy;
  ctaPrimary?: CallToAction;
  ctaSecondary?: CallToAction;
}

export interface Author {
  name: string;
  role?: string;
  avatarId?: string;
}

export interface TestimonialBlockData {
  quote: string;
  author: Author;
  source?: string;
}

export interface RichTextBlockData {
  body: string;
  alignment?: 'start' | 'center' | 'end' | 'justify';
}

export interface InfoMediaItem {
  id: string;
  title: string;
  description: string;
  media?: Media;
  bullets?: string[];
}

export interface InfoMediaBlockData {
  eyebrow?: string;
  title?: string;
  subtitle?: string;
  body?: string;
  media?: Media;
  items?: InfoMediaItem[];
}

export interface StatMetric {
  id: string;
  value: string;
  label: string;
  icon?: string;
  asOf?: string;
  tooltip?: string;
  benefit?: string;
}

export interface StatStripBlockData {
  title?: string;
  lede?: string;
  metrics: StatMetric[];
  marquee?: string[];
}

export interface AudienceCase {
  id: string;
  badge?: string;
  title: string;
  lead?: string;
  body?: string;
  bullets?: string[];
  keyFacts?: string[];
  media?: Media;
}

export interface AudienceSpotlightBlockData {
  title: string;
  subtitle?: string;
  cases: AudienceCase[];
}

export interface PackageHighlight {
  title: string;
  bullets?: string[];
}

export interface PackageOption {
  id: string;
  title: string;
  intro?: string;
  highlights?: PackageHighlight[];
}

export interface PackagePlan {
  id: string;
  title: string;
  badge?: string;
  description?: string;
  features?: string[];
  notes?: string[];
  primaryCta?: CallToAction;
  secondaryCta?: CallToAction;
}

export interface PackageSummaryBlockData {
  title: string;
  subtitle?: string;
  options?: PackageOption[];
  plans?: PackagePlan[];
  disclaimer?: string;
}

export interface FaqItem {
  id: string;
  question: string;
  answer: string;
}

export interface FaqFollowUp {
  text?: string;
  linkLabel?: string;
  href?: string;
}

export interface FaqBlockData {
  title: string;
  items: FaqItem[];
  followUp?: FaqFollowUp;
}

export interface ContactFormBlockData {
  title: string;
  intro: string;
  recipient: string;
  submitLabel: string;
  privacyHint: string;
}

export interface SectionBackground {
  mode: 'none' | 'color' | 'image';
  colorToken?: Tokens['background'];
  imageId?: string;
  attachment?: 'scroll' | 'fixed';
  overlay?: number;
}

export interface SectionStyle {
  layout?: 'normal' | 'fullwidth' | 'card';
  background?: SectionBackground;
}

export interface BlockMeta {
  anchor?: string;
  sectionStyle?: SectionStyle;
}

export interface BaseBlock<TType extends BlockType, TVariant extends BlockVariant, TData> {
  id: string;
  type: TType;
  variant: TVariant;
  data: TData;
  tokens?: Tokens;
  sectionAppearance?: 'contained' | 'full' | 'card';
  meta?: BlockMeta;
  backgroundImage?: string;
}

export type HeroBlock = BaseBlock<'hero', HeroVariant, HeroBlockData>;
export type FeatureListBlock = BaseBlock<'feature_list', FeatureListVariant, FeatureListBlockData>;
export type ProcessStepsBlock = BaseBlock<'process_steps', ProcessStepsVariant, ProcessStepsBlockData>;
export type TestimonialBlock = BaseBlock<'testimonial', TestimonialVariant, TestimonialBlockData>;
export type RichTextBlock = BaseBlock<'rich_text', RichTextVariant, RichTextBlockData>;
export type InfoMediaBlock = BaseBlock<'info_media', InfoMediaVariant, InfoMediaBlockData>;
export type CtaBlock = BaseBlock<'cta', CtaVariant, CtaBlockData>;
export type StatStripBlock = BaseBlock<'stat_strip', StatStripVariant, StatStripBlockData>;
export type AudienceSpotlightBlock = BaseBlock<'audience_spotlight', AudienceSpotlightVariant, AudienceSpotlightBlockData>;
export type PackageSummaryBlock = BaseBlock<'package_summary', PackageSummaryVariant, PackageSummaryBlockData>;
export type FaqBlock = BaseBlock<'faq', FaqVariant, FaqBlockData>;
export type ContactFormBlock = BaseBlock<'contact_form', ContactFormVariant, ContactFormBlockData>;
export type SystemModuleBlock = BaseBlock<'system_module', SystemModuleVariant, InfoMediaBlockData>;
export type CaseShowcaseBlock = BaseBlock<'case_showcase', CaseShowcaseVariant, AudienceSpotlightBlockData>;

export type BlockContract =
  | HeroBlock
  | FeatureListBlock
  | ProcessStepsBlock
  | TestimonialBlock
  | RichTextBlock
  | InfoMediaBlock
  | CtaBlock
  | StatStripBlock
  | AudienceSpotlightBlock
  | PackageSummaryBlock
  | FaqBlock
  | ContactFormBlock
  | SystemModuleBlock
  | CaseShowcaseBlock;
