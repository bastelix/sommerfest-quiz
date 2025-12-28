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
  | 'system_module'
  | 'case_showcase';

export type HeroVariant = 'centered_cta' | 'media_right' | 'media_left';
export type FeatureListVariant = 'stacked_cards' | 'icon_grid' | 'detailed-cards' | 'grid-bullets';
export type ProcessStepsVariant = 'timeline_horizontal' | 'timeline_vertical' | 'timeline';
export type TestimonialVariant = 'single_quote' | 'quote_wall';
export type RichTextVariant = 'prose';
export type InfoMediaVariant = 'stacked' | 'switcher';
export type CtaVariant = 'full_width';
export type StatStripVariant = 'three-up';
export type AudienceSpotlightVariant = 'tabs';
export type PackageSummaryVariant = 'toggle' | 'comparison-cards';
export type FaqVariant = 'accordion';
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
  | SystemModuleVariant
  | CaseShowcaseVariant;

export interface Tokens {
  background?: 'default' | 'muted' | 'primary';
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

export interface HeroBlockData {
  eyebrow?: string;
  headline: string;
  subheadline?: string;
  media?: Media;
  cta: CallToAction;
}

export interface FeatureItem {
  id: string;
  icon?: string;
  title: string;
  description: string;
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
  title?: string;
  subtitle?: string;
  body?: string;
  items?: InfoMediaItem[];
}

export interface StatMetric {
  id: string;
  value: string;
  label: string;
  asOf?: string;
  tooltip?: string;
  benefit?: string;
}

export interface StatStripBlockData {
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

export interface BaseBlock<TType extends BlockType, TVariant extends BlockVariant, TData> {
  id: string;
  type: TType;
  variant: TVariant;
  data: TData;
  tokens?: Tokens;
}

export type HeroBlock = BaseBlock<'hero', HeroVariant, HeroBlockData>;
export type FeatureListBlock = BaseBlock<'feature_list', FeatureListVariant, FeatureListBlockData>;
export type ProcessStepsBlock = BaseBlock<'process_steps', ProcessStepsVariant, ProcessStepsBlockData>;
export type TestimonialBlock = BaseBlock<'testimonial', TestimonialVariant, TestimonialBlockData>;
export type RichTextBlock = BaseBlock<'rich_text', RichTextVariant, RichTextBlockData>;
export type InfoMediaBlock = BaseBlock<'info_media', InfoMediaVariant, InfoMediaBlockData>;
export type CtaBlock = BaseBlock<'cta', CtaVariant, CallToAction>;
export type StatStripBlock = BaseBlock<'stat_strip', StatStripVariant, StatStripBlockData>;
export type AudienceSpotlightBlock = BaseBlock<'audience_spotlight', AudienceSpotlightVariant, AudienceSpotlightBlockData>;
export type PackageSummaryBlock = BaseBlock<'package_summary', PackageSummaryVariant, PackageSummaryBlockData>;
export type FaqBlock = BaseBlock<'faq', FaqVariant, FaqBlockData>;
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
  | SystemModuleBlock
  | CaseShowcaseBlock;
