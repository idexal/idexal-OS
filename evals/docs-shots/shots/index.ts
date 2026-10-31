import type { Shot } from "./shot.ts";
import { denIdexalOsWeb, denPluginDetail, denSkillEditor } from "./den-web.ts";
import {
  desktopTeamPromptCards,
  libraryAddMcpModal,
  libraryAdvancedSettings,
  libraryCreateSkillModal,
  librarySkills,
} from "./desktop.ts";
import {
  denLegacyProviderCatalogForm,
  denLegacyProviderCustomForm,
  denLegacyProviderDetail,
  denLegacyProviders,
  desktopCloudProviders,
} from "./providers.ts";
import { idexalOsWebTab } from "./web-tab.ts";

export const shots: Shot[] = [
  desktopTeamPromptCards,
  librarySkills,
  libraryCreateSkillModal,
  libraryAdvancedSettings,
  libraryAddMcpModal,
  denPluginDetail,
  denSkillEditor,
  denIdexalOsWeb,
  idexalOsWebTab,
  denLegacyProviders,
  denLegacyProviderCatalogForm,
  denLegacyProviderCustomForm,
  denLegacyProviderDetail,
  desktopCloudProviders,
];
